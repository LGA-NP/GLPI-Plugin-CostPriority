CREATE TABLE IF NOT EXISTS glpi_plugin_prioritycost_rules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entities_id INT UNSIGNED NOT NULL,
  priority INT NOT NULL,
  cost DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_ep (entities_id, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS glpi_plugin_prioritycost_configs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entities_id INT UNSIGNED NOT NULL,
  is_inherited TINYINT(1) NOT NULL DEFAULT 0,
  budgets_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_entity (entities_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
