-- Support bounded global pruning of old, already-read notifications.
-- Unread notifications are deliberately retained regardless of age.
ALTER TABLE notifications
  ADD KEY ix_notifications_retention (read_at, id);
