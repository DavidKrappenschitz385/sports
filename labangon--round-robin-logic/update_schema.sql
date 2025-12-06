ALTER TABLE teams ADD COLUMN matches_played INT DEFAULT 0;
ALTER TABLE teams ADD COLUMN score_difference INT DEFAULT 0;
-- Ensure points configuration exists with requested defaults (Win=1)
ALTER TABLE leagues ADD COLUMN points_per_win FLOAT DEFAULT 1;
ALTER TABLE leagues ADD COLUMN points_per_draw FLOAT DEFAULT 0.5;
ALTER TABLE leagues ADD COLUMN points_per_loss FLOAT DEFAULT 0;
