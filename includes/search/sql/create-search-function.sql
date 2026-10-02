-- ================================================
-- Gregius Data - PDO Search Functions
-- ================================================
-- Creates search and retrieval PostgreSQL functions for PDO connections.
-- Run this script to deploy search functions independently of the full schema setup.
--
-- Version: 1.0.0
-- Architecture: Row-per-embedding with chunk support
-- ================================================

-- ============================================
-- Function declarations removed — search_native_orchestrate is the only search entry point.
-- ============================================

-- Shared core helper: reciprocal rank fusion (RRF) score by rank position.
DROP FUNCTION IF EXISTS search_core_fuse_rrf(integer, integer);

CREATE OR REPLACE FUNCTION search_core_fuse_rrf(
    rank_position integer,
    rrf_k integer DEFAULT 60
)
RETURNS real
LANGUAGE sql
IMMUTABLE
AS $$
    SELECT CASE
        WHEN rank_position IS NULL OR rank_position < 1 THEN 0::real
        ELSE (1.0 / (GREATEST(rrf_k, 1) + rank_position))::real
    END;
$$;

-- Core vector candidate generator for multi-model retrieval.
-- Resolves the query vector and candidate set from the embedding model's registered table.
-- Handles dense embedding models (HashingTF, OpenAI, Cohere) and any future models
-- without hardcoding table names anywhere in the call chain.
DROP FUNCTION IF EXISTS search_core_vector_candidates(text, text[], integer, text, text, text);
DROP FUNCTION IF EXISTS search_core_vector_candidates(text, text[], integer, text, text, text, text);

CREATE OR REPLACE FUNCTION search_core_vector_candidates(
    search_text     text,
    post_types      text[],
    limit_count     integer,
    search_language text,
    vector_table    text,
    vector_column   text,
    precomputed_query_vector text DEFAULT NULL
)
RETURNS TABLE (
    post_id       bigint,
    source_score  real,
    post_title    text,
    post_excerpt  text,
    post_type     varchar(20),
    post_status   varchar(20),
    source        text,
    rank_position bigint
)
LANGUAGE plpgsql
SET search_path = public, pg_temp
AS $$
DECLARE
    v_query_vector       vector;
    v_valid_vector_count int := 0;
BEGIN
    IF precomputed_query_vector IS NOT NULL AND precomputed_query_vector <> '' THEN
        EXECUTE 'SELECT $1::vector' INTO v_query_vector USING precomputed_query_vector;
    END IF;

    IF v_query_vector IS NULL THEN
        -- Confirm embeddings exist before querying.
            EXECUTE format(
                'SELECT COUNT(*) FROM (SELECT 1 FROM %I WHERE embedding IS NOT NULL LIMIT 10) sub',
                vector_table
            ) INTO v_valid_vector_count;

            IF v_valid_vector_count < 1 THEN
                RETURN;
            END IF;

            -- Query vector via document averaging.
            -- Use pre-computed search_vector_weighted (GIN-indexed) instead of
            -- recomputing to_tsvector on every embedding row.
            EXECUTE format(
                'SELECT AVG(v.embedding)::vector
                 FROM (
                     SELECT v.embedding
                     FROM %I v
                     INNER JOIN wp_posts_clean pc ON v.post_id = pc.post_id
                     WHERE
                         v.embedding IS NOT NULL
                         AND pc.search_vector_weighted @@ plainto_tsquery($1::regconfig, $2)
                     ORDER BY ts_rank_cd(pc.search_vector_weighted, plainto_tsquery($1::regconfig, $2)) DESC,
                              CASE v.field_type
                                  WHEN ''title''   THEN 0
                                  WHEN ''excerpt'' THEN 1
                                  ELSE 2
                              END
                     LIMIT 10
                 ) v',
                vector_table
            ) INTO v_query_vector USING search_language, search_text;
    END IF;

    IF v_query_vector IS NULL THEN
        RETURN;
    END IF;

    -- Retrieve candidates from the registered table using dynamic SQL.
    RETURN QUERY EXECUTE format(
        'SELECT
             v.post_id::bigint,
             (1.0 - (v.embedding <=> $1) *
                 CASE v.field_type
                     WHEN ''title''   THEN 1.5
                     WHEN ''excerpt'' THEN 1.2
                     ELSE 1.0
                 END
             )::real                                                    AS source_score,
             pc.post_title_clean                                        AS post_title,
             COALESCE(pc.post_excerpt_clean,
                      LEFT(pc.post_content_clean, 200) || ''...'')     AS post_excerpt,
             p.post_type,
             p.post_status,
             ''vector''::text                                           AS source,
             row_number() OVER (ORDER BY v.embedding <=> $1, v.post_id ASC)::bigint AS rank_position
         FROM %I v
         INNER JOIN wp_posts_clean pc ON v.post_id = pc.post_id
         INNER JOIN wp_posts p         ON v.post_id = p.id
         WHERE
             v.embedding IS NOT NULL
             AND (1.0 - (v.embedding <=> $1)) > 0.5
             AND p.post_type   = ANY($2)
              AND p.post_status = ''publish''
          ORDER BY v.embedding <=> $1 ASC, v.post_id ASC
          LIMIT $3',
         vector_table
     )
    USING v_query_vector, post_types, GREATEST(limit_count, 20);

END;
$$;

-- Native search orchestrator with parallel hybrid retrieval and reciprocal rank fusion.
-- Primary native implementation used by both PDO and REST paths.
DROP FUNCTION IF EXISTS search_native_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text);
DROP FUNCTION IF EXISTS search_native_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, integer);
DROP FUNCTION IF EXISTS search_native_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, integer, text);

CREATE OR REPLACE FUNCTION search_native_orchestrate(
    search_text text,
    post_types text[] DEFAULT ARRAY['post', 'page'],
    limit_count integer DEFAULT 50,
    search_language text DEFAULT 'english',
    enable_trigram boolean DEFAULT false,
    similarity_threshold real DEFAULT 0.3,
    enable_vector boolean DEFAULT false,
    vector_table text DEFAULT 'wp_posts_hashingtf_murmur3_1024',
    vector_column text DEFAULT 'embedding',
    rrf_k integer DEFAULT 60,
    precomputed_query_vector text DEFAULT NULL
)
RETURNS TABLE (
    post_id bigint,
    relevance_score real,
    post_title text,
    post_excerpt text,
    post_type varchar(20),
    post_status varchar(20),
    match_type text
) 
LANGUAGE plpgsql
SET search_path = public, pg_temp
AS $$
DECLARE
    v_word_count int;
    v_has_short_words boolean;
BEGIN
    IF enable_trigram THEN
        EXECUTE format('SET pg_trgm.word_similarity_threshold = %s', similarity_threshold);
        EXECUTE 'SET LOCAL enable_seqscan = off';
    END IF;

    IF enable_trigram THEN
        v_word_count := array_length(string_to_array(trim(search_text), ' '), 1);
        SELECT COUNT(*) = 0 INTO v_has_short_words
        FROM unnest(string_to_array(lower(trim(search_text)), ' ')) AS word
        WHERE length(word) > 2;

        IF v_has_short_words THEN
            enable_trigram := false;
        END IF;
    END IF;

    RETURN QUERY
    WITH fts_results AS (
        SELECT
            pc.post_id,
            ts_rank_cd(
                pc.search_vector_weighted,
                plainto_tsquery(search_language::regconfig, search_text)
            )::real AS source_score,
            pc.post_title_clean AS post_title,
            COALESCE(
                pc.post_excerpt_clean,
                LEFT(pc.post_content_clean, 200) || '...'
            ) AS post_excerpt,
            p.post_type,
            p.post_status,
            'fts'::text AS source,
            row_number() OVER (
                ORDER BY ts_rank_cd(
                    pc.search_vector_weighted,
                    plainto_tsquery(search_language::regconfig, search_text)
                ) DESC
            ) AS rank_position
        FROM wp_posts_clean pc
        INNER JOIN wp_posts p ON pc.post_id = p.id
        WHERE
            pc.search_vector_weighted @@ plainto_tsquery(search_language::regconfig, search_text)
            AND p.post_type = ANY(post_types)
            AND p.post_status = 'publish'
        LIMIT GREATEST(limit_count, 20)
    ),
    trigram_results AS (
        SELECT
            tr.post_id,
            MAX(tr.source_score)::real AS source_score,
            MAX(tr.post_title) AS post_title,
            MAX(tr.post_excerpt) AS post_excerpt,
            MAX(tr.post_type)::varchar(20) AS post_type,
            MAX(tr.post_status)::varchar(20) AS post_status,
            'trigram'::text AS source,
            row_number() OVER (ORDER BY MAX(tr.source_score) DESC)::bigint AS rank_position
        FROM (
            (SELECT
                pc.post_id,
                (1.0 - (search_text <<-> pc.post_title_clean))::real AS source_score,
                pc.post_title_clean AS post_title,
                COALESCE(
                    pc.post_excerpt_clean,
                    LEFT(pc.post_content_clean, 200) || '...'
                ) AS post_excerpt,
                p.post_type,
                p.post_status
            FROM wp_posts_clean pc
            INNER JOIN wp_posts p ON pc.post_id = p.id
            WHERE
                enable_trigram = true
                AND p.post_type = ANY(post_types)
                AND p.post_status = 'publish'
            ORDER BY search_text <<-> pc.post_title_clean ASC
            LIMIT GREATEST(limit_count, 20))

            UNION ALL

            (SELECT
                pc.post_id,
                (1.0 - (search_text <<-> pc.post_content_clean))::real AS source_score,
                pc.post_title_clean AS post_title,
                COALESCE(
                    pc.post_excerpt_clean,
                    LEFT(pc.post_content_clean, 200) || '...'
                ) AS post_excerpt,
                p.post_type,
                p.post_status
            FROM wp_posts_clean pc
            INNER JOIN wp_posts p ON pc.post_id = p.id
            WHERE
                enable_trigram = true
                AND p.post_type = ANY(post_types)
                AND p.post_status = 'publish'
            ORDER BY search_text <<-> pc.post_content_clean ASC
            LIMIT GREATEST(limit_count, 20))
        ) tr
        GROUP BY tr.post_id
        ORDER BY MAX(tr.source_score) DESC
        LIMIT GREATEST(limit_count, 20)
    ),
    vector_results AS (
        SELECT
            vc.post_id,
            vc.source_score,
            vc.post_title,
            vc.post_excerpt,
            vc.post_type,
            vc.post_status,
            'vector'::text AS source,
            vc.rank_position
        FROM search_core_vector_candidates(
            search_text, post_types, limit_count, search_language, vector_table, vector_column, precomputed_query_vector
        ) vc
        WHERE
            enable_vector = true
    ),
    all_candidates AS (
        SELECT * FROM fts_results
        UNION ALL
        SELECT * FROM trigram_results
        UNION ALL
        SELECT * FROM vector_results
    ),
    fused AS (
        SELECT
            c.post_id,
            MAX(c.post_title) AS post_title,
            MAX(c.post_excerpt) AS post_excerpt,
            MAX(c.post_type)::varchar(20) AS post_type,
            MAX(c.post_status)::varchar(20) AS post_status,
            SUM(search_core_fuse_rrf(c.rank_position::integer, rrf_k))::real AS fused_score,
            BOOL_OR(c.source = 'fts') AS has_fts,
            BOOL_OR(c.source = 'trigram') AS has_trigram,
            BOOL_OR(c.source = 'vector') AS has_vector
        FROM all_candidates c
        GROUP BY c.post_id
    )
    SELECT
        f.post_id,
        f.fused_score AS relevance_score,
        f.post_title,
        f.post_excerpt,
        f.post_type,
        f.post_status,
        CASE
            WHEN f.has_vector AND (f.has_fts OR f.has_trigram) THEN 'blended_rrf'
            WHEN f.has_vector THEN 'vector'
            WHEN f.has_trigram THEN 'trigram'
            ELSE 'fts'
        END AS match_type
    FROM fused f
    ORDER BY f.fused_score DESC
    LIMIT limit_count;

END;
$$;

-- RAG orchestrator (always blended when source is available) with reciprocal rank fusion.
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, integer);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, integer, jsonb);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, jsonb, integer);

CREATE OR REPLACE FUNCTION search_rag_orchestrate(
    search_text text,
    post_types text[] DEFAULT ARRAY['post', 'page'],
    limit_count integer DEFAULT 50,
    search_language text DEFAULT 'english',
    enable_trigram boolean DEFAULT true,
    similarity_threshold real DEFAULT 0.3,
    enable_vector boolean DEFAULT true,
    vector_table text DEFAULT 'wp_posts_hashingtf_murmur3_1024',
    vector_column text DEFAULT 'embedding',
    metadata_filter jsonb DEFAULT '{}'::jsonb,
    rrf_k integer DEFAULT 60
)
RETURNS TABLE (
    post_id bigint,
    relevance_score real,
    post_title text,
    post_excerpt text,
    post_type varchar(20),
    post_status varchar(20),
    match_type text
)
LANGUAGE plpgsql
SET search_path = public, pg_temp
AS $$
DECLARE
    v_word_count int;
    v_has_short_words boolean;
BEGIN
    IF enable_trigram THEN
        EXECUTE format('SET pg_trgm.word_similarity_threshold = %s', similarity_threshold);
        EXECUTE 'SET LOCAL enable_seqscan = off';
    END IF;

    IF enable_trigram THEN
        v_word_count := array_length(string_to_array(trim(search_text), ' '), 1);
        SELECT COUNT(*) = 0 INTO v_has_short_words
        FROM unnest(string_to_array(lower(trim(search_text)), ' ')) AS word
        WHERE length(word) > 2;

        IF v_has_short_words THEN
            enable_trigram := false;
        END IF;
    END IF;

    RETURN QUERY
    WITH fts_results AS (
        SELECT
            pc.post_id,
            ts_rank_cd(
                pc.search_vector_weighted,
                plainto_tsquery(search_language::regconfig, search_text)
            )::real AS source_score,
            pc.post_title_clean AS post_title,
            COALESCE(
                pc.post_excerpt_clean,
                LEFT(pc.post_content_clean, 200) || '...'
            ) AS post_excerpt,
            p.post_type,
            p.post_status,
            'fts'::text AS source,
            row_number() OVER (
                ORDER BY ts_rank_cd(
                    pc.search_vector_weighted,
                    plainto_tsquery(search_language::regconfig, search_text)
                ) DESC,
                pc.post_id ASC
            ) AS rank_position
        FROM wp_posts_clean pc
        INNER JOIN wp_posts p ON pc.post_id = p.id
        WHERE
            pc.search_vector_weighted @@ plainto_tsquery(search_language::regconfig, search_text)
            AND p.post_type = ANY(post_types)
            AND p.post_status = 'publish'
            AND (
                metadata_filter = '{}'::jsonb
                OR (
                    (metadata_filter->>'filter_operator' IS NULL OR metadata_filter->>'filter_operator' = 'AND')
                    AND COALESCE(pc.metadata_manifest, '{}'::jsonb) @> (metadata_filter - 'filter_operator')
                )
                OR (
                    metadata_filter->>'filter_operator' = 'OR'
                    AND EXISTS (
                        SELECT 1 FROM jsonb_array_elements(metadata_filter->'taxonomy_manifest') AS f
                        WHERE COALESCE(pc.metadata_manifest, '{}'::jsonb) @>
                            jsonb_build_object('taxonomy_manifest', jsonb_build_array(f))
                    )
                )
            )
        LIMIT GREATEST(limit_count, 20)
    ),
    trigram_results AS (
        SELECT
            tr.post_id,
            MAX(tr.source_score)::real AS source_score,
            MAX(tr.post_title) AS post_title,
            MAX(tr.post_excerpt) AS post_excerpt,
            MAX(tr.post_type)::varchar(20) AS post_type,
            MAX(tr.post_status)::varchar(20) AS post_status,
            'trigram'::text AS source,
            row_number() OVER (ORDER BY MAX(tr.source_score) DESC)::bigint AS rank_position
        FROM (
            (SELECT
                pc.post_id,
                (1.0 - (search_text <<-> pc.post_title_clean))::real AS source_score,
                pc.post_title_clean AS post_title,
                COALESCE(
                    pc.post_excerpt_clean,
                    LEFT(pc.post_content_clean, 200) || '...'
                ) AS post_excerpt,
                p.post_type,
                p.post_status
            FROM wp_posts_clean pc
            INNER JOIN wp_posts p ON pc.post_id = p.id
            WHERE
                enable_trigram = true
                AND p.post_type = ANY(post_types)
                AND p.post_status = 'publish'
                AND (
                    metadata_filter = '{}'::jsonb
                    OR (
                        (metadata_filter->>'filter_operator' IS NULL OR metadata_filter->>'filter_operator' = 'AND')
                        AND COALESCE(pc.metadata_manifest, '{}'::jsonb) @> (metadata_filter - 'filter_operator')
                    )
                    OR (
                        metadata_filter->>'filter_operator' = 'OR'
                        AND EXISTS (
                            SELECT 1 FROM jsonb_array_elements(metadata_filter->'taxonomy_manifest') AS f
                            WHERE COALESCE(pc.metadata_manifest, '{}'::jsonb) @>
                                jsonb_build_object('taxonomy_manifest', jsonb_build_array(f))
                        )
                    )
                )
            ORDER BY search_text <<-> pc.post_title_clean ASC
            LIMIT GREATEST(limit_count, 20))

            UNION ALL

            (SELECT
                pc.post_id,
                (1.0 - (search_text <<-> pc.post_content_clean))::real AS source_score,
                pc.post_title_clean AS post_title,
                COALESCE(
                    pc.post_excerpt_clean,
                    LEFT(pc.post_content_clean, 200) || '...'
                ) AS post_excerpt,
                p.post_type,
                p.post_status
            FROM wp_posts_clean pc
            INNER JOIN wp_posts p ON pc.post_id = p.id
            WHERE
                enable_trigram = true
                AND p.post_type = ANY(post_types)
                AND p.post_status = 'publish'
                AND (
                    metadata_filter = '{}'::jsonb
                    OR (
                        (metadata_filter->>'filter_operator' IS NULL OR metadata_filter->>'filter_operator' = 'AND')
                        AND COALESCE(pc.metadata_manifest, '{}'::jsonb) @> (metadata_filter - 'filter_operator')
                    )
                    OR (
                        metadata_filter->>'filter_operator' = 'OR'
                        AND EXISTS (
                            SELECT 1 FROM jsonb_array_elements(metadata_filter->'taxonomy_manifest') AS f
                            WHERE COALESCE(pc.metadata_manifest, '{}'::jsonb) @>
                                jsonb_build_object('taxonomy_manifest', jsonb_build_array(f))
                        )
                    )
                )
            ORDER BY search_text <<-> pc.post_content_clean ASC
            LIMIT GREATEST(limit_count, 20))
        ) tr
        GROUP BY tr.post_id
    ),
    vector_results AS (
        SELECT vc.*
        FROM search_core_vector_candidates(
            search_text, post_types, limit_count, search_language, vector_table, vector_column
        ) vc
        INNER JOIN wp_posts_clean pc ON pc.post_id = vc.post_id
        WHERE
            enable_vector = true
            AND (
                metadata_filter = '{}'::jsonb
                OR (
                    (metadata_filter->>'filter_operator' IS NULL OR metadata_filter->>'filter_operator' = 'AND')
                    AND COALESCE(pc.metadata_manifest, '{}'::jsonb) @> (metadata_filter - 'filter_operator')
                )
                OR (
                    metadata_filter->>'filter_operator' = 'OR'
                    AND EXISTS (
                        SELECT 1 FROM jsonb_array_elements(metadata_filter->'taxonomy_manifest') AS f
                        WHERE COALESCE(pc.metadata_manifest, '{}'::jsonb) @>
                            jsonb_build_object('taxonomy_manifest', jsonb_build_array(f))
                    )
                )
            )
    ),
    all_candidates AS (
        SELECT * FROM fts_results
        UNION ALL
        SELECT * FROM trigram_results
        UNION ALL
        SELECT * FROM vector_results
    ),
    fused AS (
        SELECT
            c.post_id,
            MAX(c.post_title) AS post_title,
            MAX(c.post_excerpt) AS post_excerpt,
            MAX(c.post_type)::varchar(20) AS post_type,
            MAX(c.post_status)::varchar(20) AS post_status,
            SUM(search_core_fuse_rrf(c.rank_position::integer, rrf_k))::real AS fused_score,
            BOOL_OR(c.source = 'fts') AS has_fts,
            BOOL_OR(c.source = 'trigram') AS has_trigram,
            BOOL_OR(c.source = 'vector') AS has_vector
        FROM all_candidates c
        GROUP BY c.post_id
    )
    SELECT
        f.post_id,
        f.fused_score AS relevance_score,
        f.post_title,
        f.post_excerpt,
        f.post_type,
        f.post_status,
        CASE
            WHEN f.has_vector AND (f.has_fts OR f.has_trigram) THEN 'blended_rrf'
            WHEN f.has_vector THEN 'vector'
            WHEN f.has_trigram THEN 'trigram'
            ELSE 'fts'
        END AS match_type
    FROM fused f
    ORDER BY f.fused_score DESC, f.post_id ASC
    LIMIT limit_count;
END;
$$;

-- Drop old helper function signatures
DROP FUNCTION IF EXISTS gg_generate_search_vector(text, text, text, text, text);
DROP FUNCTION IF EXISTS gg_generate_search_vector(text, text, text, text);
DROP FUNCTION IF EXISTS gg_generate_search_vector(text, text, text, text, text, integer);

-- Helper function: Generate search vector using document averaging
-- For row-per-embedding schema, averages embeddings from matching documents
CREATE OR REPLACE FUNCTION gg_generate_search_vector(
    search_text text,
    search_language text DEFAULT 'english',
    vector_table_name text DEFAULT 'wp_posts_hashingtf_murmur3_1024',
    vector_column_name text DEFAULT 'embedding'
)
RETURNS TABLE (vector vector) 
LANGUAGE plpgsql
SET search_path = public, pg_temp
AS $$
BEGIN
    -- Use pre-computed search_vector_weighted (GIN-indexed) when language
    -- matches the indexed column, avoiding per-row to_tsvector recomputation.
    IF search_language = 'english' THEN
        RETURN QUERY EXECUTE format('
            SELECT AVG(v.embedding)::vector
            FROM (
                SELECT v.embedding, v.field_type
                FROM %I v
                INNER JOIN wp_posts_clean pc ON v.post_id = pc.post_id
                WHERE 
                    v.embedding IS NOT NULL
                    AND pc.search_vector_weighted @@ plainto_tsquery(%L::regconfig, %L)
                ORDER BY 
                    ts_rank_cd(pc.search_vector_weighted, plainto_tsquery(%L::regconfig, %L)) DESC,
                    CASE v.field_type WHEN ''title'' THEN 0 WHEN ''excerpt'' THEN 1 ELSE 2 END
                LIMIT 10
            ) v
        ', vector_table_name, search_language, search_text, search_language, search_text);
    ELSE
        RETURN QUERY EXECUTE format('
            SELECT AVG(v.embedding)::vector
            FROM (
                SELECT v.embedding, v.field_type
                FROM %I v
                INNER JOIN wp_posts_clean pc ON v.post_id = pc.post_id
                WHERE 
                    v.embedding IS NOT NULL
                    AND to_tsvector(%L::regconfig, pc.post_title_clean || '' '' || pc.post_content_clean) @@ plainto_tsquery(%L::regconfig, %L)
                ORDER BY 
                    CASE v.field_type WHEN ''title'' THEN 0 WHEN ''excerpt'' THEN 1 ELSE 2 END
                LIMIT 10
            ) v
        ', vector_table_name, search_language, search_language, search_text);
    END IF;
END;
$$;

-- ============================================
-- Index Creation (run after table creation)
-- ============================================

-- GIN Trigram Indexes (for typo tolerance)
CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_trigram_content 
ON wp_posts_clean USING GIN (post_content_clean gin_trgm_ops);

CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_trigram_title 
ON wp_posts_clean USING GIN (post_title_clean gin_trgm_ops);

CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_trigram_excerpt 
ON wp_posts_clean USING GIN (post_excerpt_clean gin_trgm_ops);

-- GiST Trigram Indexes (for efficient ORDER BY similarity with LIMIT pushdown)
CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_trigram_title_gist 
ON wp_posts_clean USING GIST (post_title_clean gist_trgm_ops);

CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_trigram_content_gist 
ON wp_posts_clean USING GIST (post_content_clean gist_trgm_ops);

-- FTS Index (for fast full-text lookups)
CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_fts 
ON wp_posts_clean 
USING GIN (to_tsvector('english', post_title_clean || ' ' || post_content_clean));

-- Note: Requires pg_trgm extension for typo tolerance and pgvector extension for vector search
-- IVFFlat indexes for vector search are created in the main schema file (postgrest-schema.sql)

-- ============================================
-- Example Usage
-- ============================================

-- FTS only:
-- SELECT * FROM search_native_orchestrate('wordpress database', ARRAY['post', 'page'], 20, 'english');
-- SELECT * FROM search_native_orchestrate('base de datos', ARRAY['post', 'page'], 20, 'spanish');

-- With typo tolerance:
-- SELECT * FROM search_native_orchestrate('audiolgy', ARRAY['post'], 20, 'english', true, 0.3);
-- SELECT * FROM search_native_orchestrate('databse', ARRAY['post'], 20, 'english', true, 0.3);

-- With vector semantic search (parallel hybrid RRF):
-- SELECT * FROM search_native_orchestrate('hearing loss treatment', ARRAY['post'], 20, 'english', true, 0.3, true);
-- SELECT * FROM search_native_orchestrate('vehicle transportation', ARRAY['post'], 30, 'english', true, 0.3, true);
-- 
-- ============================================
-- Parameters
-- ============================================
-- 1. search_text: Text to search for
-- 2. post_types: Array of post types to search (default: ['post', 'page'])
-- 3. limit_count: Maximum results to return (default: 50)
-- 4. search_language: PostgreSQL text search config (default: 'english')
-- 5. enable_trigram: Enable typo tolerance with word similarity matching (default: false)
-- 6. similarity_threshold: Minimum similarity score 0.0-1.0 (default: 0.3)
-- 7. enable_vector: Enable semantic vector search for related content (default: false)
-- 8. vector_table: Name of the vector table (default: 'wp_posts_hashingtf_murmur3_1024')
-- 9. vector_column: Name of the embedding column (default: 'embedding')
--
-- ============================================
-- Returns
-- ============================================
-- - post_id: WordPress post ID
-- - relevance_score: Ranking score (higher = more relevant)
-- - post_title: Post title
-- - post_excerpt: Post excerpt or first 200 chars
-- - post_type: WordPress post type
-- - post_status: WordPress post status
-- - match_type: 'fts' (exact match), 'trigram' (similarity match), or 'vector' (semantic match)
--
-- ============================================
-- Features
-- ============================================
-- 1. Row-per-embedding schema: field_type ('title', 'excerpt', 'chunk'), chunk_index, embedding
-- 2. Language-specific stemming and stop words (15+ languages)
-- 3. Field weighting (title matches rank higher than content)
-- 4. Typo tolerance using word_similarity (<%>) operator
-- 5. Semantic vector search (finds conceptually related content)
-- 6. Progressive enhancement (FTS → trigram → vector)
-- 7. Graceful degradation (works without pg_trgm/pgvector if disabled)
-- 8. Deduplication (each post appears once, prioritizing FTS > trigram > vector)
--
-- ============================================
-- Search Priority
-- ============================================
-- Tier 1 (FTS): Exact matches with stemming (highest priority)
-- Tier 2 (Trigram): Typo-tolerant matches (medium priority) - triggered when FTS < 5 results
-- Tier 3 (Vector): Semantic/conceptual matches - triggered when FTS+Trigram < 5 results
--
-- ============================================
-- Supported Languages
-- ============================================
-- english, spanish, french, german, italian, portuguese, russian, simple
-- Language parameter should match WordPress locale (e.g., en_US -> english, es_ES -> spanish)
