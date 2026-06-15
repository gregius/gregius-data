=== RAG Chat Block ===
Contributors: Hector Jarquin, Gregius
Tags: chat, ai, rag, search, assistant
Requires at least: 6.1
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chat interface using TF-IDF semantic search and OpenAI.

== Description ==

The RAG Chat block provides an AI-powered conversational interface that uses Retrieval-Augmented Generation (RAG) with TF-IDF semantic search and OpenAI's language models.

**Features:**

* Real-time chat interface with conversation history
* TF-IDF 300-dimensional semantic search for context retrieval
* OpenAI integration for natural language responses
* Source citations with clickable links
* Configurable PostgreSQL connection selection
* Customizable placeholder text
* Responsive design with block editor support

== Installation ==

This block is part of the Gregius Data plugin and is automatically registered when the plugin is active.

1. Ensure Gregius Data plugin is installed and activated
2. Configure at least one PostgreSQL connection
3. Set up OpenAI API credentials in plugin settings
4. Add the "RAG Chat (TF-IDF 300)" block to any post or page

== Frequently Asked Questions ==

= What is RAG? =

Retrieval-Augmented Generation (RAG) combines semantic search with AI language models to provide accurate, context-aware responses based on your content.

= What databases are supported? =

Currently supports PostgreSQL with TF-IDF 300-dimensional vector embeddings. MySQL 9.0+ support is planned.

= Do I need an OpenAI API key? =

Yes, this block requires a valid OpenAI API key configured in the Gregius Data plugin settings.

== Changelog ==

= 1.0.0 =
* Initial release
* TF-IDF 300D semantic search
* OpenAI chat integration
* Conversation history
* Source citations
* Block editor support
