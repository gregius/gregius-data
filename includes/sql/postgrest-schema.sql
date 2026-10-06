-- ================================================
-- Gregius Data - Supabase Schema Setup
-- ================================================
-- Creates the complete PostgreSQL schema for the Gregius Data plugin.
-- Run this script in your Supabase SQL Editor before activating the plugin.
--
-- Version: 1.0.0
-- Architecture: Row-per-embedding with chunk support
-- ================================================

-- Enable required PostgreSQL extensions
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Schema metadata table (tracks version)
CREATE TABLE IF NOT EXISTS gg_schema_meta (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Insert schema version
INSERT INTO gg_schema_meta (key, value) 
VALUES ('version', '1.0.0')
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

-- WordPress Posts table (main content)
CREATE TABLE IF NOT EXISTS wp_posts (
    id BIGINT PRIMARY KEY,
    post_author BIGINT DEFAULT 0,
    post_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    post_date_gmt TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    post_content TEXT DEFAULT '',
    post_title TEXT DEFAULT '',
    post_excerpt TEXT DEFAULT '',
    post_status VARCHAR(20) DEFAULT 'publish',
    comment_status VARCHAR(20) DEFAULT 'open',
    ping_status VARCHAR(20) DEFAULT 'open',
    post_password VARCHAR(255) DEFAULT '',
    post_name VARCHAR(200) DEFAULT '',
    to_ping TEXT DEFAULT '',
    pinged TEXT DEFAULT '',
    post_modified TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    post_modified_gmt TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    post_content_filtered TEXT DEFAULT '',
    post_parent BIGINT DEFAULT 0,
    guid VARCHAR(255) DEFAULT '',
    menu_order INT DEFAULT 0,
    post_type VARCHAR(20) DEFAULT 'post',
    post_mime_type VARCHAR(100) DEFAULT '',
    comment_count BIGINT DEFAULT 0,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_wp_posts_type_status ON wp_posts(post_type, post_status);
CREATE INDEX IF NOT EXISTS idx_wp_posts_date ON wp_posts(post_date DESC);
CREATE INDEX IF NOT EXISTS idx_wp_posts_author ON wp_posts(post_author);
CREATE INDEX IF NOT EXISTS idx_wp_posts_parent ON wp_posts(post_parent);
CREATE INDEX IF NOT EXISTS idx_wp_posts_name ON wp_posts(post_name);

-- Composite index for main query performance (Status + Type + Date + Included fields)
CREATE INDEX IF NOT EXISTS idx_wp_posts_status_type_date 
ON wp_posts(post_status, post_type, post_date DESC) 
INCLUDE (post_title, post_excerpt);

-- WordPress Posts Clean (stripped content for AI/search)
CREATE TABLE IF NOT EXISTS wp_posts_clean (
    post_id BIGINT PRIMARY KEY REFERENCES wp_posts(id) ON DELETE CASCADE,
    post_title_clean TEXT DEFAULT '',
    post_content_clean TEXT DEFAULT '',
    post_excerpt_clean TEXT DEFAULT '',
    metadata_manifest JSONB DEFAULT '{}'::jsonb,
    cleaned_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    content_hash VARCHAR(32) DEFAULT '',
    cleaning_version VARCHAR(10) DEFAULT '1.0',
    word_count INT DEFAULT 0,
    reading_time_minutes INT DEFAULT 0,
    search_vector_weighted tsvector -- Precomputed weighted tsvector for fast FTS
);

-- Add search_vector_weighted column if it doesn't exist (for schema updates)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'wp_posts_clean' 
        AND column_name = 'search_vector_weighted'
    ) THEN
        ALTER TABLE wp_posts_clean ADD COLUMN search_vector_weighted tsvector;
    END IF;
END $$;

-- Add metadata_manifest column if it doesn't exist (for schema updates)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'wp_posts_clean'
        AND column_name = 'metadata_manifest'
    ) THEN
        ALTER TABLE wp_posts_clean ADD COLUMN metadata_manifest JSONB DEFAULT '{}'::jsonb;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_hash ON wp_posts_clean(content_hash);

-- Composite index for faster joins (excludes content to save space)
CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_post_id_include 
ON wp_posts_clean(post_id) 
INCLUDE (post_title_clean, post_excerpt_clean);

-- GIN index on precomputed search_vector_weighted (FAST!)
CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_search_vector 
ON wp_posts_clean USING GIN (search_vector_weighted);

-- JSONB containment index for deterministic metadata filters.
CREATE INDEX IF NOT EXISTS idx_wp_posts_clean_metadata_manifest_gin
ON wp_posts_clean USING GIN (metadata_manifest jsonb_path_ops);

-- Trigger to automatically update search_vector_weighted on INSERT/UPDATE
-- NOTE: Field weights can be customized via WordPress filter hooks:
--   - apply_filters('gg_data_search_title_weight', 'A', $connection_name)
--   - apply_filters('gg_data_search_content_weight', 'B', $connection_name)
-- PostgreSQL tsvector weights: A=1.0, B=0.4, C=0.2, D=0.1

-- Drop trigger function if exists (handles parameter changes)
DROP FUNCTION IF EXISTS wp_posts_clean_search_vector_update() CASCADE;

CREATE OR REPLACE FUNCTION wp_posts_clean_search_vector_update() 
RETURNS trigger AS $$
BEGIN
    NEW.search_vector_weighted := 
        setweight(to_tsvector('english', COALESCE(NEW.post_title_clean, '')), 'A') ||
        setweight(to_tsvector('english', COALESCE(NEW.post_content_clean, '')), 'B');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SET search_path = public, extensions, pg_temp;

DROP TRIGGER IF EXISTS tsvector_update ON wp_posts_clean;
CREATE TRIGGER tsvector_update 
BEFORE INSERT OR UPDATE ON wp_posts_clean
FOR EACH ROW EXECUTE FUNCTION wp_posts_clean_search_vector_update();

-- Populate existing rows (run once after schema creation)
UPDATE wp_posts_clean 
SET search_vector_weighted = 
    setweight(to_tsvector('english', COALESCE(post_title_clean, '')), 'A') ||
    setweight(to_tsvector('english', COALESCE(post_content_clean, '')), 'B')
WHERE search_vector_weighted IS NULL;

-- WordPress Post Meta
CREATE TABLE IF NOT EXISTS wp_postmeta (
    meta_id BIGINT PRIMARY KEY,
    post_id BIGINT DEFAULT 0 REFERENCES wp_posts(id) ON DELETE CASCADE,
    meta_key VARCHAR(255) DEFAULT NULL,
    meta_value TEXT DEFAULT NULL,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_wp_postmeta_post ON wp_postmeta(post_id);
CREATE INDEX IF NOT EXISTS idx_wp_postmeta_key ON wp_postmeta(meta_key);

-- WordPress Terms
CREATE TABLE IF NOT EXISTS wp_terms (
    term_id BIGINT PRIMARY KEY,
    name VARCHAR(200) DEFAULT '',
    slug VARCHAR(200) DEFAULT '',
    term_group BIGINT DEFAULT 0,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_wp_terms_slug ON wp_terms(slug);
CREATE INDEX IF NOT EXISTS idx_wp_terms_name ON wp_terms(name);

-- WordPress Term Taxonomy
CREATE TABLE IF NOT EXISTS wp_term_taxonomy (
    term_taxonomy_id BIGINT PRIMARY KEY,
    term_id BIGINT DEFAULT 0 REFERENCES wp_terms(term_id) ON DELETE CASCADE,
    taxonomy VARCHAR(32) DEFAULT '',
    description TEXT DEFAULT '',
    parent BIGINT DEFAULT 0,
    count BIGINT DEFAULT 0,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_wp_term_taxonomy_term ON wp_term_taxonomy(term_id);
CREATE INDEX IF NOT EXISTS idx_wp_term_taxonomy_taxonomy ON wp_term_taxonomy(taxonomy);

-- WordPress Term Relationships
CREATE TABLE IF NOT EXISTS wp_term_relationships (
    object_id BIGINT DEFAULT 0 REFERENCES wp_posts(id) ON DELETE CASCADE,
    term_taxonomy_id BIGINT DEFAULT 0 REFERENCES wp_term_taxonomy(term_taxonomy_id) ON DELETE CASCADE,
    term_order INT DEFAULT 0,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (object_id, term_taxonomy_id)
);

CREATE INDEX IF NOT EXISTS idx_wp_term_relationships_term ON wp_term_relationships(term_taxonomy_id);

-- ================================================
-- CHUNK ARCHITECTURE
-- ================================================
-- Shared text chunks table (model-agnostic)
-- All embedding models read from this table
-- ================================================

CREATE TABLE IF NOT EXISTS wp_posts_chunks (
    chunk_id SERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES wp_posts(id) ON DELETE CASCADE,
    chunk_index INTEGER NOT NULL,
    chunk_text TEXT NOT NULL,
    chunk_hash VARCHAR(32) NOT NULL,
    source_hash VARCHAR(32) NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    
    UNIQUE(post_id, chunk_index)
);

CREATE INDEX IF NOT EXISTS idx_chunks_post_id ON wp_posts_chunks(post_id);
CREATE INDEX IF NOT EXISTS idx_chunks_hash ON wp_posts_chunks(chunk_hash);
CREATE INDEX IF NOT EXISTS idx_chunks_source_hash ON wp_posts_chunks(source_hash);

-- ================================================
-- ROW-PER-EMBEDDING SCHEMA
-- ================================================
-- Each embedding is stored as a separate row
-- field_type: 'title', 'excerpt', or 'chunk'
-- chunk_index: NULL for title/excerpt, 0-N for chunks
-- ================================================

-- OpenAI text-embedding-3-small (1536 dimensions)
CREATE TABLE IF NOT EXISTS wp_posts_openai_text_embedding_3_small_1536 (
    id SERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES wp_posts(id) ON DELETE CASCADE,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
    chunk_index INTEGER,
    embedding vector(1536),
    content_hash VARCHAR(32) NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    cost DECIMAL(10, 8) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    model_used VARCHAR(100) DEFAULT 'text-embedding-3-small',
    error_message TEXT,
    
    UNIQUE(post_id, field_type, chunk_index),
    CHECK (
        (field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
        (field_type = 'chunk' AND chunk_index IS NOT NULL)
    )
);

-- Single HNSW index for text-embedding-3-small
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_small_embedding_hnsw 
ON wp_posts_openai_text_embedding_3_small_1536 USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);

CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_small_post_id ON wp_posts_openai_text_embedding_3_small_1536(post_id);
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_small_field_type ON wp_posts_openai_text_embedding_3_small_1536(field_type);
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_small_status ON wp_posts_openai_text_embedding_3_small_1536(status);
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_small_hash ON wp_posts_openai_text_embedding_3_small_1536(content_hash);

-- OpenAI text-embedding-3-large (3072 dimensions, halfvec for storage efficiency)
CREATE TABLE IF NOT EXISTS wp_posts_openai_text_embedding_3_large_3072 (
    id SERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES wp_posts(id) ON DELETE CASCADE,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
    chunk_index INTEGER,
    embedding halfvec(3072),
    content_hash VARCHAR(32) NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    cost DECIMAL(10, 8) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    model_used VARCHAR(100) DEFAULT 'text-embedding-3-large',
    error_message TEXT,
    
    UNIQUE(post_id, field_type, chunk_index),
    CHECK (
        (field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
        (field_type = 'chunk' AND chunk_index IS NOT NULL)
    )
);

-- Single HNSW index for text-embedding-3-large (halfvec)
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_large_embedding_hnsw 
ON wp_posts_openai_text_embedding_3_large_3072 USING hnsw (embedding halfvec_cosine_ops)
WITH (m = 16, ef_construction = 64);

CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_large_post_id ON wp_posts_openai_text_embedding_3_large_3072(post_id);
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_large_field_type ON wp_posts_openai_text_embedding_3_large_3072(field_type);
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_large_status ON wp_posts_openai_text_embedding_3_large_3072(status);
CREATE INDEX IF NOT EXISTS idx_wp_posts_openai_3_large_hash ON wp_posts_openai_text_embedding_3_large_3072(content_hash);

-- Gemini gemini-embedding-2 (3072 dimensions, halfvec for HNSW compatibility)
CREATE TABLE IF NOT EXISTS wp_posts_gemini_gemini_embedding_2_3072 (
    id SERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES wp_posts(id) ON DELETE CASCADE,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
    chunk_index INTEGER,
    embedding halfvec(3072),
    content_hash VARCHAR(32) NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    cost DECIMAL(10, 8) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    model_used VARCHAR(100) DEFAULT 'gemini-embedding-2',
    error_message TEXT,
    
    UNIQUE(post_id, field_type, chunk_index),
    CHECK (
        (field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
        (field_type = 'chunk' AND chunk_index IS NOT NULL)
    )
);

CREATE INDEX IF NOT EXISTS idx_wp_posts_gemini_2_embedding_hnsw 
ON wp_posts_gemini_gemini_embedding_2_3072 USING hnsw (embedding halfvec_cosine_ops)
WITH (m = 16, ef_construction = 64);

CREATE INDEX IF NOT EXISTS idx_wp_posts_gemini_2_post_id ON wp_posts_gemini_gemini_embedding_2_3072(post_id);
CREATE INDEX IF NOT EXISTS idx_wp_posts_gemini_2_field_type ON wp_posts_gemini_gemini_embedding_2_3072(field_type);
CREATE INDEX IF NOT EXISTS idx_wp_posts_gemini_2_status ON wp_posts_gemini_gemini_embedding_2_3072(status);
CREATE INDEX IF NOT EXISTS idx_wp_posts_gemini_2_hash ON wp_posts_gemini_gemini_embedding_2_3072(content_hash);

-- Voyage voyage-4 (1024 dimensions)
CREATE TABLE IF NOT EXISTS wp_posts_voyage_voyage_4_1024 (
    id SERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES wp_posts(id) ON DELETE CASCADE,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
    chunk_index INTEGER,
    embedding vector(1024),
    content_hash VARCHAR(32) NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    cost DECIMAL(10, 8) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    model_used VARCHAR(100) DEFAULT 'voyage-4',
    error_message TEXT,
    
    UNIQUE(post_id, field_type, chunk_index),
    CHECK (
        (field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
        (field_type = 'chunk' AND chunk_index IS NOT NULL)
    )
);

CREATE INDEX IF NOT EXISTS idx_wp_posts_voyage_4_embedding_hnsw 
ON wp_posts_voyage_voyage_4_1024 USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);

CREATE INDEX IF NOT EXISTS idx_wp_posts_voyage_4_post_id ON wp_posts_voyage_voyage_4_1024(post_id);
CREATE INDEX IF NOT EXISTS idx_wp_posts_voyage_4_field_type ON wp_posts_voyage_voyage_4_1024(field_type);
CREATE INDEX IF NOT EXISTS idx_wp_posts_voyage_4_status ON wp_posts_voyage_voyage_4_1024(status);
CREATE INDEX IF NOT EXISTS idx_wp_posts_voyage_4_hash ON wp_posts_voyage_voyage_4_1024(content_hash);

-- Cohere embed-v4.0 (1536 dimensions)
CREATE TABLE IF NOT EXISTS wp_posts_cohere_embed_v40_1536 (
    id SERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES wp_posts(id) ON DELETE CASCADE,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
    chunk_index INTEGER,
    embedding vector(1536),
    content_hash VARCHAR(32) NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    cost DECIMAL(10, 8) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    model_used VARCHAR(100) DEFAULT 'embed-v4.0',
    error_message TEXT,
    
    UNIQUE(post_id, field_type, chunk_index),
    CHECK (
        (field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
        (field_type = 'chunk' AND chunk_index IS NOT NULL)
    )
);

CREATE INDEX IF NOT EXISTS idx_wp_posts_cohere_v40_embedding_hnsw 
ON wp_posts_cohere_embed_v40_1536 USING hnsw (embedding vector_cosine_ops)
WITH (m = 16, ef_construction = 64);

CREATE INDEX IF NOT EXISTS idx_wp_posts_cohere_v40_post_id ON wp_posts_cohere_embed_v40_1536(post_id);
CREATE INDEX IF NOT EXISTS idx_wp_posts_cohere_v40_field_type ON wp_posts_cohere_embed_v40_1536(field_type);
CREATE INDEX IF NOT EXISTS idx_wp_posts_cohere_v40_status ON wp_posts_cohere_embed_v40_1536(status);
CREATE INDEX IF NOT EXISTS idx_wp_posts_cohere_v40_hash ON wp_posts_cohere_embed_v40_1536(content_hash);

-- ================================================
-- HashingTF Murmur3 1024D (stateless internal embeddings)
-- Stateless feature hashing — no vocabulary required.
-- ================================================
CREATE TABLE IF NOT EXISTS wp_posts_hashingtf_murmur3_1024 (
    id SERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES wp_posts(id) ON DELETE CASCADE,
    field_type VARCHAR(20) NOT NULL CHECK (field_type IN ('title', 'excerpt', 'chunk')),
    chunk_index INTEGER,
    embedding vector(1024),
    content_hash VARCHAR(32) NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    generated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    tokenizer_version INTEGER NOT NULL DEFAULT 1,
    error_message TEXT,
    UNIQUE(post_id, field_type, chunk_index),
    CHECK (
        (field_type IN ('title', 'excerpt') AND chunk_index IS NULL) OR
        (field_type = 'chunk' AND chunk_index IS NOT NULL)
    )
);

CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_embedding_hnsw
    ON wp_posts_hashingtf_murmur3_1024 USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 64);
CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_post_id ON wp_posts_hashingtf_murmur3_1024(post_id);
CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_field_type ON wp_posts_hashingtf_murmur3_1024(field_type);
CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_status ON wp_posts_hashingtf_murmur3_1024(status);
CREATE INDEX IF NOT EXISTS idx_wp_posts_hashingtf_1024_tokenizer_version ON wp_posts_hashingtf_murmur3_1024(tokenizer_version);

-- ================================================
-- RPC FUNCTIONS
-- ================================================

-- Drop existing function signatures before recreating
-- This handles parameter name changes that PostgreSQL doesn't allow in-place
DROP FUNCTION IF EXISTS get_posts_needing_vectors(text, int);
DROP FUNCTION IF EXISTS get_schema_status();

-- Get posts needing vector generation (row-per-embedding)
-- Returns posts that don't have a title embedding yet for the specified model
-- Updated to accept any model name dynamically
CREATE OR REPLACE FUNCTION get_posts_needing_vectors(
    target_model TEXT,
    batch_size INT DEFAULT 10
)
RETURNS TABLE (
    post_id BIGINT,
    post_title_clean TEXT,
    post_excerpt_clean TEXT,
    post_content_clean TEXT
)
LANGUAGE plpgsql
SET search_path = public, extensions, pg_temp
AS $$
BEGIN
    -- Find posts without a title embedding (simplest check for "needs vectors")
    -- Dynamic table name: wp_posts_{target_model}
    RETURN QUERY EXECUTE format('
        SELECT 
            c.post_id, 
            c.post_title_clean, 
            c.post_excerpt_clean,
            c.post_content_clean
        FROM wp_posts_clean c
        WHERE NOT EXISTS (
            SELECT 1 FROM wp_posts_%I v 
            WHERE v.post_id = c.post_id 
            AND v.field_type = ''title''
        )
        ORDER BY c.post_id ASC
        LIMIT %s
    ', target_model, batch_size);
END;
$$;

-- Generic RAG context retrieval for model-resolved vector stores.
DROP FUNCTION IF EXISTS search_rag_get_context(vector, int, int, text, text[]);

CREATE OR REPLACE FUNCTION search_rag_get_context(
    query_vector vector,
    match_count int DEFAULT 5,
    max_tokens int DEFAULT 2000,
    vector_table_name text DEFAULT 'wp_posts_hashingtf_murmur3_1024',
    post_types text[] DEFAULT NULL
)
RETURNS TABLE (
    post_id bigint,
    chunk_index integer,
    chunk_text text,
    similarity float,
    post_title text,
    running_tokens int
)
LANGUAGE plpgsql
SET search_path = public, extensions, pg_temp
AS $$
DECLARE
    v_post_type_filter text := '';
BEGIN
    IF post_types IS NOT NULL AND array_length( post_types, 1 ) > 0 THEN
        v_post_type_filter := ' AND p_filter.post_type = ANY($4) ';
    END IF;

    RETURN QUERY EXECUTE format(
        'WITH ranked_chunks AS (
            SELECT
                v.post_id,
                v.chunk_index,
                ch.chunk_text,
                ch.token_count,
                1 - ( v.embedding <=> $1 ) as similarity
            FROM %I v
            JOIN wp_posts_chunks ch ON v.post_id = ch.post_id AND v.chunk_index = ch.chunk_index
            JOIN wp_posts p_filter ON v.post_id = p_filter.id
            WHERE
                v.field_type = ''chunk''
                AND v.embedding IS NOT NULL
                AND p_filter.post_status = ''publish''
                %s
            ORDER BY v.embedding <=> $1
            LIMIT $2 * 2
        ),
        with_running_total AS (
            SELECT
                rc.*,
                SUM( rc.token_count ) OVER ( ORDER BY rc.similarity DESC ) as running_tokens
            FROM ranked_chunks rc
        )
        SELECT
            wrt.post_id,
            wrt.chunk_index,
            wrt.chunk_text,
            wrt.similarity::float,
            p.post_title,
            wrt.running_tokens::int
        FROM with_running_total wrt
        JOIN wp_posts p ON wrt.post_id = p.id
        WHERE wrt.running_tokens <= $3
        ORDER BY wrt.similarity DESC
        LIMIT $2',
        vector_table_name,
        v_post_type_filter
    )
    USING query_vector, match_count, max_tokens, post_types;
END;
$$;

-- Schema status check
CREATE OR REPLACE FUNCTION get_schema_status()
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, extensions, pg_temp
AS $$
DECLARE
    v_version text;
    v_post_count bigint;
    v_chunk_count bigint;
    v_openai_small_count bigint;
    v_openai_large_count bigint;
    v_gemini_count bigint;
    v_voyage_count bigint;
    v_cohere_count bigint;
    v_hashingtf_count bigint;
    v_extensions jsonb;
BEGIN
    -- Get schema version
    SELECT value INTO v_version 
    FROM gg_schema_meta 
    WHERE key = 'version';
    
    -- Get counts
    SELECT COUNT(*) INTO v_post_count FROM wp_posts;
    SELECT COUNT(*) INTO v_chunk_count FROM wp_posts_chunks;
    SELECT COUNT(*) INTO v_openai_small_count FROM wp_posts_openai_text_embedding_3_small_1536;
    SELECT COUNT(*) INTO v_openai_large_count FROM wp_posts_openai_text_embedding_3_large_3072;
    SELECT COUNT(*) INTO v_gemini_count FROM wp_posts_gemini_gemini_embedding_2_3072;
    SELECT COUNT(*) INTO v_voyage_count FROM wp_posts_voyage_voyage_4_1024;
    SELECT COUNT(*) INTO v_cohere_count FROM wp_posts_cohere_embed_v40_1536;
    SELECT COUNT(*) INTO v_hashingtf_count FROM wp_posts_hashingtf_murmur3_1024;
    
    -- Check extensions
    SELECT jsonb_object_agg(extname, extversion) INTO v_extensions
    FROM pg_extension
    WHERE extname IN ('vector', 'pg_trgm');
    
    RETURN jsonb_build_object(
        'success', true,
        'version', COALESCE(v_version, 'unknown'),
        'post_count', COALESCE(v_post_count, 0),
        'chunk_count', COALESCE(v_chunk_count, 0),
        'openai_small_embedding_count', COALESCE(v_openai_small_count, 0),
        'openai_large_embedding_count', COALESCE(v_openai_large_count, 0),
        'gemini_embedding_count', COALESCE(v_gemini_count, 0),
        'voyage_embedding_count', COALESCE(v_voyage_count, 0),
        'cohere_embedding_count', COALESCE(v_cohere_count, 0),
        'hashingtf_embedding_count', COALESCE(v_hashingtf_count, 0),
        'extensions', COALESCE(v_extensions, '{}'::jsonb),
        'checked_at', NOW()
    );
END;
$$;

-- ================================================
-- Row Level Security (RLS) Policies
-- ================================================

ALTER TABLE wp_posts ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_clean ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_chunks ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_postmeta ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_terms ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_term_taxonomy ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_term_relationships ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_openai_text_embedding_3_small_1536 ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_openai_text_embedding_3_large_3072 ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_gemini_gemini_embedding_2_3072 ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_voyage_voyage_4_1024 ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_cohere_embed_v40_1536 ENABLE ROW LEVEL SECURITY;
ALTER TABLE wp_posts_hashingtf_murmur3_1024 ENABLE ROW LEVEL SECURITY;
ALTER TABLE gg_schema_meta ENABLE ROW LEVEL SECURITY;

-- Drop existing policies (if any) before recreating
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_clean;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_chunks;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_postmeta;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_terms;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_term_taxonomy;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_term_relationships;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_openai_text_embedding_3_small_1536;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_openai_text_embedding_3_large_3072;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_gemini_gemini_embedding_2_3072;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_voyage_voyage_4_1024;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_cohere_embed_v40_1536;
DROP POLICY IF EXISTS "Enable all access for service_role" ON wp_posts_hashingtf_murmur3_1024;
DROP POLICY IF EXISTS "Enable all access for service_role" ON gg_schema_meta;

-- Create permissive policies for service_role
CREATE POLICY "Enable all access for service_role" ON wp_posts FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_clean FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_chunks FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_postmeta FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_terms FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_term_taxonomy FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_term_relationships FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_openai_text_embedding_3_small_1536 FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_openai_text_embedding_3_large_3072 FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_gemini_gemini_embedding_2_3072 FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_voyage_voyage_4_1024 FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_cohere_embed_v40_1536 FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON wp_posts_hashingtf_murmur3_1024 FOR ALL TO service_role USING (true) WITH CHECK (true);
CREATE POLICY "Enable all access for service_role" ON gg_schema_meta FOR ALL TO service_role USING (true) WITH CHECK (true);

-- Read-only policies for the anonymous role (used by the server-side search/RAG
-- functions, which run as `anon`). Scoped to the tables those functions read.
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_clean;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_chunks;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_openai_text_embedding_3_small_1536;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_openai_text_embedding_3_large_3072;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_gemini_gemini_embedding_2_3072;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_voyage_voyage_4_1024;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_cohere_embed_v40_1536;
DROP POLICY IF EXISTS "Enable read access for anon" ON wp_posts_hashingtf_murmur3_1024;

CREATE POLICY "Enable read access for anon" ON wp_posts FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_clean FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_chunks FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_openai_text_embedding_3_small_1536 FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_openai_text_embedding_3_large_3072 FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_gemini_gemini_embedding_2_3072 FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_voyage_voyage_4_1024 FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_cohere_embed_v40_1536 FOR SELECT TO anon USING (true);
CREATE POLICY "Enable read access for anon" ON wp_posts_hashingtf_murmur3_1024 FOR SELECT TO anon USING (true);

-- ================================================
-- Full-Text Search Functions
-- ================================================

 -- Architecture: Row-per-Embedding (v1.0.0)

-- Shared core helper: reciprocal rank fusion (RRF) score by rank position.
DROP FUNCTION IF EXISTS search_core_fuse_rrf(integer, integer);

CREATE OR REPLACE FUNCTION search_core_fuse_rrf(
    rank_position integer,
    rrf_k integer DEFAULT 60
)
RETURNS real
LANGUAGE sql
IMMUTABLE
SET search_path = public, extensions, pg_temp
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
SET search_path = public, extensions, pg_temp
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
             row_number() OVER (ORDER BY v.embedding <=> $1)::bigint   AS rank_position
         FROM %I v
         INNER JOIN wp_posts_clean pc ON v.post_id = pc.post_id
         INNER JOIN wp_posts p         ON v.post_id = p.id
         WHERE
             v.embedding IS NOT NULL
             AND (1.0 - (v.embedding <=> $1)) > 0.5
             AND p.post_type   = ANY($2)
              AND p.post_status = ''publish''
          ORDER BY v.embedding <=> $1 ASC
          LIMIT $3',
         vector_table
     )
    USING v_query_vector, post_types, GREATEST(limit_count, 20);

END;
$$;

-- Native search orchestrator with parallel hybrid retrieval and reciprocal rank fusion.
DROP FUNCTION IF EXISTS search_native_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text);
DROP FUNCTION IF EXISTS search_native_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, integer);
DROP FUNCTION IF EXISTS search_native_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, integer, text);
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
SET search_path = public, extensions, pg_temp
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
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, integer);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, integer, jsonb);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, jsonb, integer);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, integer);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, integer, jsonb);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, text, jsonb, integer);
DROP FUNCTION IF EXISTS search_rag_orchestrate(text, text[], integer, text, boolean, real, boolean, text, text, jsonb, integer, text);

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
SET search_path = public, extensions, pg_temp
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
            search_text, post_types, limit_count, search_language, vector_table, vector_column, precomputed_query_vector
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
    ORDER BY f.fused_score DESC
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
SET search_path = public, extensions, pg_temp
AS $$
BEGIN
    -- Use the pre-computed search_vector_weighted column (GIN-indexed) for all
    -- languages; the trigger builds the column in the configured search language.
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


-- Note: Requires pg_trgm extension for typo tolerance and pgvector extension for vector search

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

-- Success message
DO $$
BEGIN
    RAISE NOTICE 'Gregius Data schema v1.0.0 created successfully. Row-per-embedding architecture with chunk support is ready.';
END $$;
