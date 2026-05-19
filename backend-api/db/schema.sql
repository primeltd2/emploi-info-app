CREATE TABLE IF NOT EXISTS offers (
  id TEXT PRIMARY KEY,
  title TEXT NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '',
  notice TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL DEFAULT '',
  city TEXT NOT NULL DEFAULT '',
  banner TEXT NOT NULL DEFAULT '',
  buttons JSONB NOT NULL DEFAULT '[]'::jsonb,
  alignment TEXT NOT NULL DEFAULT 'left',
  urgent BOOLEAN NOT NULL DEFAULT FALSE,
  published BOOLEAN NOT NULL DEFAULT FALSE,
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS offers_public_feed_idx
  ON offers (published, published_at DESC, created_at DESC);

CREATE TABLE IF NOT EXISTS catalog_lists (
  name TEXT PRIMARY KEY,
  items JSONB NOT NULL DEFAULT '[]'::jsonb,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS content_items (
  id TEXT PRIMARY KEY,
  section TEXT NOT NULL,
  payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  published BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS content_items_section_idx
  ON content_items (section, published, created_at DESC);

CREATE TABLE IF NOT EXISTS ads (
  id TEXT PRIMARY KEY,
  payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ads_active_idx
  ON ads (active, created_at DESC);

CREATE TABLE IF NOT EXISTS app_settings (
  name TEXT PRIMARY KEY,
  value JSONB NOT NULL DEFAULT '{}'::jsonb,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS android_tokens (
  token TEXT PRIMARY KEY,
  platform TEXT NOT NULL DEFAULT 'android',
  app TEXT NOT NULL DEFAULT 'emploi-info',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notification_sent (
  offer_id TEXT PRIMARY KEY REFERENCES offers(id) ON DELETE CASCADE,
  context JSONB NOT NULL DEFAULT '{}'::jsonb,
  sent_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notification_reminders (
  id BIGSERIAL PRIMARY KEY,
  offer_id TEXT NOT NULL UNIQUE REFERENCES offers(id) ON DELETE CASCADE,
  offer JSONB NOT NULL,
  send_after TIMESTAMPTZ NOT NULL,
  sent BOOLEAN NOT NULL DEFAULT FALSE,
  sent_at TIMESTAMPTZ,
  result JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
