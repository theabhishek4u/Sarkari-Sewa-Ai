# Sarkari Sewa AI — Installation & Supabase pgvector Setup Guide

This guide walks you through setting up **Sarkari Sewa AI** on WordPress and configuring **Supabase PostgreSQL + pgvector**.

---

## 1. Supabase Database Configuration (pgvector)

Log in to your [Supabase Dashboard](https://supabase.com/dashboard) and open the **SQL Editor**. Run the following SQL script:

```sql
-- 1. Enable the pgvector extension
create extension if not exists vector;

-- 2. Create the documents table for Sarkari Sewa AI
create table if not exists sarkari_ai_documents (
  id bigserial primary key,
  post_id bigint not null,
  chunk_index int not null default 0,
  content text not null,
  metadata jsonb default '{}'::jsonb,
  embedding vector(1536), -- Use 1536 for OpenAI text-embedding-3-small, or 768 for Gemini text-embedding-004
  updated_at timestamp with time zone default timezone('utc'::text, now()) not null,
  unique(post_id, chunk_index)
);

-- 3. Create HNSW index for high-speed similarity search
create index if not exists sarkari_ai_documents_embedding_idx
  on sarkari_ai_documents
  using hnsw (embedding vector_cosine_ops);

-- 4. Create the match_documents RPC stored procedure
create or replace function match_documents (
  query_embedding vector(1536),
  match_threshold float,
  match_count int
)
returns table (
  id bigint,
  post_id bigint,
  chunk_index int,
  content text,
  metadata jsonb,
  similarity float
)
language plpgsql
as $$
begin
  return query
  select
    sarkari_ai_documents.id,
    sarkari_ai_documents.post_id,
    sarkari_ai_documents.chunk_index,
    sarkari_ai_documents.content,
    sarkari_ai_documents.metadata,
    1 - (sarkari_ai_documents.embedding <=> query_embedding) as similarity
  from sarkari_ai_documents
  where 1 - (sarkari_ai_documents.embedding <=> query_embedding) > match_threshold
  order by similarity desc
  limit match_count;
end;
$$;
```

---

## 2. WordPress Plugin Installation

1. Go to **WordPress Admin > Plugins > Add New > Upload Plugin**.
2. Select the `sarkari-sewa-ai.zip` file and click **Install Now**.
3. Click **Activate Plugin**.

---

## 3. Configuration Steps

1. In WordPress Admin sidebar, click on **Sarkari Sewa AI > Settings**.
2. Under **AI Provider Tab**:
   - Select your provider (e.g. OpenAI or Gemini).
   - Enter your API Key.
3. Under **Vector DB Tab**:
   - Enter your **Supabase Project URL** (e.g., `https://xyzproject.supabase.co`).
   - Enter your **Supabase Service Role Key**.
   - Confirm table name: `sarkari_ai_documents`.
4. Click **Save All Settings**.
5. Go to **Dashboard** and click **Run Health Check** to verify that WordPress DB, AI Provider, and Supabase are reporting `Connected`.
6. Go to **Content Index** and click **Start Bulk Indexing**.

Your floating AI chatbot is now live on your site!
