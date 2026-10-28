CREATE DATABASE IF NOT EXISTS carebridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE carebridge;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NOT NULL,
  phone_verified_at DATETIME NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('patient','hospital_admin','platform_admin') NOT NULL DEFAULT 'patient',
  status ENUM('active','pending','suspended','disabled') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patient_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  patient_code VARCHAR(32) NOT NULL UNIQUE,
  date_of_birth DATE NULL,
  phone VARCHAR(40) NULL,
  address VARCHAR(500) NULL,
  emergency_contact_name VARCHAR(120) NULL,
  emergency_contact_phone VARCHAR(40) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_patient_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  organization_type ENUM('hospital','clinic','laboratory','independent_practice') NOT NULL DEFAULT 'hospital',
  license_number VARCHAR(120) NOT NULL,
  address VARCHAR(500) NOT NULL,
  phone VARCHAR(40) NULL,
  approval_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  application_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_org_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
  CONSTRAINT fk_org_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_org_status_created (approval_status, created_at),
  INDEX idx_org_owner (owner_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_role ENUM('hospital_admin','doctor','nurse','receptionist','lab_staff','billing_staff') NOT NULL,
  status ENUM('invited','active','suspended') NOT NULL DEFAULT 'invited',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_member_role (organization_id, user_id, member_role),
  CONSTRAINT fk_member_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patient_provider_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_user_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  link_status ENUM('pending','active','revoked') NOT NULL DEFAULT 'pending',
  verified_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_patient_provider (patient_user_id, organization_id),
  CONSTRAINT fk_ppl_patient FOREIGN KEY (patient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ppl_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_ppl_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS consent_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_user_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  granted_by BIGINT UNSIGNED NOT NULL,
  scope VARCHAR(120) NOT NULL,
  purpose VARCHAR(500) NOT NULL,
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_consent_patient FOREIGN KEY (patient_user_id) REFERENCES users(id),
  CONSTRAINT fk_consent_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_consent_grantor FOREIGN KEY (granted_by) REFERENCES users(id),
  INDEX idx_consent_patient_org (patient_user_id, organization_id, revoked_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_department_name (organization_id, name),
  CONSTRAINT fk_department_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctor_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  license_number VARCHAR(120) NOT NULL,
  specialty VARCHAR(120) NULL,
  verification_status ENUM('pending','verified','rejected','suspended') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doctor_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_doctor_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_doctor_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  INDEX idx_doctor_org_status (organization_id, verification_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  patient_user_id BIGINT UNSIGNED NOT NULL,
  doctor_user_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  reason VARCHAR(500) NULL,
  status ENUM('requested','confirmed','checked_in','completed','cancelled','no_show') NOT NULL DEFAULT 'requested',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_appointment_patient FOREIGN KEY (patient_user_id) REFERENCES users(id),
  CONSTRAINT fk_appointment_doctor FOREIGN KEY (doctor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_appointment_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  INDEX idx_appointments_org_time (organization_id, starts_at),
  INDEX idx_appointments_patient_time (patient_user_id, starts_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS encounters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  patient_user_id BIGINT UNSIGNED NOT NULL,
  doctor_user_id BIGINT UNSIGNED NULL,
  clinician_name VARCHAR(180) NOT NULL,
  clinician_license VARCHAR(120) NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  encounter_type ENUM('outpatient','emergency','inpatient','telehealth','other') NOT NULL DEFAULT 'outpatient',
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  summary VARCHAR(1000) NULL,
  status ENUM('open','closed','amended') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_encounter_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_encounter_patient FOREIGN KEY (patient_user_id) REFERENCES users(id),
  CONSTRAINT fk_encounter_doctor FOREIGN KEY (doctor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_encounter_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  INDEX idx_encounters_patient_time (patient_user_id, occurred_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medical_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  record_type ENUM('clinical_note','diagnosis','procedure','allergy','immunization','other') NOT NULL DEFAULT 'clinical_note',
  title VARCHAR(180) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  amended_from BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_record_encounter FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  CONSTRAINT fk_record_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_record_amended FOREIGN KEY (amended_from) REFERENCES medical_records(id) ON DELETE SET NULL,
  INDEX idx_records_encounter (encounter_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prescriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  prescribed_by BIGINT UNSIGNED NOT NULL,
  medicine_name VARCHAR(180) NOT NULL,
  dosage VARCHAR(120) NOT NULL,
  instructions VARCHAR(500) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prescription_encounter FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  CONSTRAINT fk_prescription_doctor FOREIGN KEY (prescribed_by) REFERENCES users(id),
  INDEX idx_prescription_encounter (encounter_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lab_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  test_name VARCHAR(180) NOT NULL,
  result_summary VARCHAR(1000) NULL,
  result_status ENUM('requested','in_progress','available','cancelled') NOT NULL DEFAULT 'requested',
  report_storage_key VARCHAR(255) NULL,
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lab_encounter FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  INDEX idx_lab_encounter (encounter_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  message VARCHAR(1000) NOT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user (user_id, read_at, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details VARCHAR(1000) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type, entity_id, created_at),
  INDEX idx_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS pending_registrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL, verification_method ENUM('email','sms') NOT NULL,
  code_hash VARCHAR(255) NOT NULL, payload_json JSON NOT NULL, expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pending_email (email), INDEX idx_pending_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, method ENUM('email','sms') NOT NULL,
  code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_reset_expiry (expires_at)
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS public_provider_directory (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180) NOT NULL, organization_type VARCHAR(80) NOT NULL, address VARCHAR(500) NOT NULL, phone VARCHAR(100) NOT NULL, email VARCHAR(190) NULL, official_url VARCHAR(500) NOT NULL, source_url VARCHAR(500) NOT NULL, location_label VARCHAR(120) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_public_provider_name (name)
) ENGINE=InnoDB;

INSERT IGNORE INTO public_provider_directory(name,organization_type,address,phone,email,official_url,source_url,location_label) VALUES
('Bir Hospital','Government hospital','Mahaboudha, Kathmandu, Bagmati, Nepal','+977-1-5321119, +977-1-5321988, +977-1-5322865','admin.birhospital@nams.org.np','https://birhospital.gov.np/','https://www.birhospital.gov.np/en/contact','Kathmandu'),
('Tribhuvan University Teaching Hospital','Teaching hospital','Maharajgunj, Kathmandu, Bagmati Pradesh, Nepal','+977-1-4512505',NULL,'https://tuth.org.np/','https://tuth.org.np/','Kathmandu'),
('Patan Hospital / Patan Academy of Health Sciences','Teaching hospital','Lagankhel, Lalitpur, Nepal','+977-1-5422278, +977-1-5422266','pahs@pahs.edu.np','https://web.pahs.edu.np/','https://web.pahs.edu.np/contact-us/','Lalitpur'),
('Patan International Hospital','Hospital','96 Shankhadhar Sakhwaa Marga, Lukhusi, Lalitpur, Nepal','+977-1-5402100, +977-1-5402200','pataninternationalhospital@gmail.com','https://pataninternationalhospital.com.np/','https://pataninternationalhospital.com.np/contact/','Lalitpur');


-- Provider verification documents are stored outside the public web root.
CREATE TABLE IF NOT EXISTS organization_verification_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL UNIQUE,
  stored_filename VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_org_verification_document_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Logs Patient-ID lookup/access requests without exposing medical records publicly.
CREATE TABLE IF NOT EXISTS patient_record_access_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_user_id BIGINT UNSIGNED NOT NULL,
  requester_name VARCHAR(120) NOT NULL,
  requester_email VARCHAR(190) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('pending','reviewed','approved','denied') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  CONSTRAINT fk_record_access_request_patient FOREIGN KEY (patient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_record_access_patient (patient_user_id, status, created_at)
) ENGINE=InnoDB;
