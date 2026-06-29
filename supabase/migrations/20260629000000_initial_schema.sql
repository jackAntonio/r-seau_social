-- Schéma initial - Réseau Social
-- Généré le 2026-06-29

-- Utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id              SERIAL PRIMARY KEY,
    photo           TEXT DEFAULT '',
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    sexe            VARCHAR(20) DEFAULT '',
    contact         VARCHAR(50) DEFAULT '',
    password        VARCHAR(255) NOT NULL,
    code            VARCHAR(10) DEFAULT '',
    is_verified     BOOLEAN DEFAULT FALSE,
    date_naissance  DATE,
    adresse         TEXT DEFAULT '',
    totp_secret     VARCHAR(64) DEFAULT NULL,
    totp_enabled    BOOLEAN DEFAULT FALSE,
    role            VARCHAR(20) DEFAULT 'user' CHECK (role IN ('user','moderateur','admin')),
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Posts
CREATE TABLE IF NOT EXISTS posts (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       VARCHAR(20) DEFAULT 'text',
    content    TEXT DEFAULT '',
    media      TEXT DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_posts_created ON posts(created_at DESC);

-- Commentaires
CREATE TABLE IF NOT EXISTS comments (
    id         SERIAL PRIMARY KEY,
    post_id    INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content    TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Likes avec type like/dislike (feed.php)
CREATE TABLE IF NOT EXISTS post_likes (
    id      SERIAL PRIMARY KEY,
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type    VARCHAR(10) DEFAULT 'like' CHECK (type IN ('like','dislike')),
    UNIQUE (post_id, user_id)
);

-- Likes simples (accueil)
CREATE TABLE IF NOT EXISTS likes (
    id      SERIAL PRIMARY KEY,
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (post_id, user_id)
);

-- Amis
CREATE TABLE IF NOT EXISTS friends (
    id         SERIAL PRIMARY KEY,
    from_user  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status     VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (from_user, to_user)
);

-- Messages
CREATE TABLE IF NOT EXISTS messages (
    id          SERIAL PRIMARY KEY,
    sender_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content     TEXT DEFAULT '',
    file        TEXT DEFAULT NULL,
    file_type   VARCHAR(100) DEFAULT NULL,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_messages_conv ON messages(sender_id, receiver_id, created_at);

-- Stories (expire après 24h)
CREATE TABLE IF NOT EXISTS stories (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    image      TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    expires_at TIMESTAMPTZ DEFAULT NOW() + INTERVAL '24 hours'
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content    TEXT NOT NULL,
    link       TEXT DEFAULT '',
    is_read    BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, is_read);

-- Rate limiting connexion
CREATE TABLE IF NOT EXISTS login_attempts (
    id           SERIAL PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    email        VARCHAR(255) DEFAULT '',
    attempted_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_attempts_ip ON login_attempts(ip, attempted_at);

-- Logs d'audit
CREATE TABLE IF NOT EXISTS audit_logs (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id   INTEGER DEFAULT NULL,
    ip          VARCHAR(45) DEFAULT NULL,
    user_agent  VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);
