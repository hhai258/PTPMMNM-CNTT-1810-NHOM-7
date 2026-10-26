-- Hệ thống quản lý cấu hình bật/tắt tính năng (Feature Flag Manager)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS end_users, decision_logs, change_logs, rules, version_flags, versions,
    group_members, user_groups, flags, memberships, environments, projects, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Thành viên dự án và quyền: viewer (chỉ xem) / editor (được sửa cấu hình) / admin (quản trị: sửa + quản lý thành viên)
CREATE TABLE memberships (
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('viewer','editor','admin') NOT NULL,
  PRIMARY KEY (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Môi trường: thử nghiệm / vận hành mô phỏng. active_version_id = bản đang phát hành.
CREATE TABLE environments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  env_key VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  api_key VARCHAR(64) NOT NULL UNIQUE,
  active_version_id INT NULL,
  UNIQUE (project_id, env_key),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE flags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  flag_key VARCHAR(60) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE (project_id, flag_key),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  group_key VARCHAR(40) NOT NULL,
  name VARCHAR(100) NOT NULL,
  UNIQUE (project_id, group_key),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mã người dùng cuối (dữ liệu giả) dùng để mô phỏng và kiểm thử
CREATE TABLE end_users (
  project_id INT NOT NULL,
  end_user_id VARCHAR(60) NOT NULL,
  PRIMARY KEY (project_id, end_user_id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mã người dùng cuối (giả) thuộc nhóm nào
CREATE TABLE group_members (
  group_id INT NOT NULL,
  end_user_id VARCHAR(60) NOT NULL,
  PRIMARY KEY (group_id, end_user_id),
  FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mỗi version là một bản cấu hình đầy đủ của một môi trường.
CREATE TABLE versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  environment_id INT NOT NULL,
  version_no INT NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TIMESTAMP NULL,
  UNIQUE (environment_id, version_no),
  FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Giá trị mặc định của từng tính năng trong một version
CREATE TABLE version_flags (
  version_id INT NOT NULL,
  flag_id INT NOT NULL,
  default_enabled TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (version_id, flag_id),
  FOREIGN KEY (version_id) REFERENCES versions(id) ON DELETE CASCADE,
  FOREIGN KEY (flag_id) REFERENCES flags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quy tắc: priority nhỏ xét trước; quy tắc đầu tiên khớp quyết định kết quả.
-- user_list: target = các mã người dùng cách nhau bằng dấu phẩy
-- group: target = group_key, kèm tỷ lệ percentage
-- percentage: áp dụng tỷ lệ cho mọi người dùng (target để trống)
CREATE TABLE rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  version_id INT NOT NULL,
  flag_id INT NOT NULL,
  priority INT NOT NULL,
  rule_type ENUM('user_list','group','percentage') NOT NULL,
  target TEXT NOT NULL,
  percentage TINYINT UNSIGNED NOT NULL DEFAULT 100,
  result_enabled TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (version_id) REFERENCES versions(id) ON DELETE CASCADE,
  FOREIGN KEY (flag_id) REFERENCES flags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lịch sử thay đổi và lý do bật/tắt theo từng phiên bản
CREATE TABLE change_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  environment_id INT NOT NULL,
  version_id INT NULL,
  user_id INT NOT NULL,
  action VARCHAR(40) NOT NULL,
  reason VARCHAR(255) NOT NULL DEFAULT '',
  detail TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (environment_id, version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nhật ký các quyết định API đã trả
CREATE TABLE decision_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  environment_id INT NOT NULL,
  version_id INT NOT NULL,
  flag_key VARCHAR(60) NOT NULL,
  end_user_id VARCHAR(60) NOT NULL,
  enabled TINYINT(1) NOT NULL,
  reason VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (environment_id, version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
