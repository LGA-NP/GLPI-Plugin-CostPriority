CREATE TABLE IF NOT EXISTS glpi_plugin_prioritycost_rules (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 entities_id INT UNSIGNED NOT NULL,
 priority INT NOT NULL,
 cost INT NOT NULL,
 budgets_id INT UNSIGNED NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uniq_ep (entities_id,priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
