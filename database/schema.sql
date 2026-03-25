-- Pilora (ERP BTP) - schéma MySQL socle (multi-tenant)
-- Note: valeurs ENUM stockées en codes ASCII pour éviter les soucis de collations/encodages.

SET NAMES utf8mb4;

-- ========== COMPANY ==========
CREATE TABLE IF NOT EXISTS Company (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  companyKind ENUM('tenant','platform') NOT NULL DEFAULT 'tenant',
  billingEmail VARCHAR(255) NULL,
  status ENUM('active','suspended','disabled') NOT NULL DEFAULT 'active',
  billingPlan VARCHAR(80) NULL,
  billingStatus ENUM('trial','active','past_due','cancelled') NULL,
  billingCycle ENUM('monthly','annual') NULL,
  maxSeats INT UNSIGNED NULL,
  subscriptionRenewsAt DATE NULL,
  externalBillingRef VARCHAR(120) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_company_status (status),
  KEY idx_company_kind (companyKind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== USERS ==========
CREATE TABLE IF NOT EXISTS `User` (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  passwordHash VARCHAR(255) NOT NULL,
  fullName VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  status ENUM('active','inactive','pending','invited','disabled') NOT NULL DEFAULT 'active',
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_company_email (companyId, email),
  KEY idx_user_companyId (companyId),
  CONSTRAINT fk_user_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== ROLES / PERMISSIONS ==========
CREATE TABLE IF NOT EXISTS Role (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope ENUM('tenant','platform') NOT NULL DEFAULT 'tenant',
  companyId BIGINT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(100) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_scope_company_name (scope, companyId, name),
  KEY idx_role_scope (scope),
  KEY idx_role_companyId (companyId),
  CONSTRAINT fk_role_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Permission (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope ENUM('tenant','platform') NOT NULL DEFAULT 'tenant',
  companyId BIGINT UNSIGNED NULL,
  code VARCHAR(150) NOT NULL,
  description VARCHAR(255) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permission_scope_company_code (scope, companyId, code),
  KEY idx_permission_scope (scope),
  KEY idx_permission_companyId (companyId),
  CONSTRAINT fk_permission_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS UserRole (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  userId BIGINT UNSIGNED NOT NULL,
  roleId BIGINT UNSIGNED NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_userRole_user_role (userId, roleId),
  KEY idx_userRole_companyId (companyId),
  KEY idx_userRole_userId (userId),
  KEY idx_userRole_roleId (roleId),
  CONSTRAINT fk_userRole_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_userRole_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_userRole_role
    FOREIGN KEY (roleId) REFERENCES Role (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS RolePermission (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  roleId BIGINT UNSIGNED NOT NULL,
  permissionId BIGINT UNSIGNED NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rolePermission_company_role_perm (companyId, roleId, permissionId),
  KEY idx_rolePermission_companyId (companyId),
  KEY idx_rolePermission_roleId (roleId),
  KEY idx_rolePermission_permissionId (permissionId),
  CONSTRAINT fk_rolePermission_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_rolePermission_role
    FOREIGN KEY (roleId) REFERENCES Role (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rolePermission_permission
    FOREIGN KEY (permissionId) REFERENCES Permission (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== SESSIONS ==========
CREATE TABLE IF NOT EXISTS UserSession (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  userId BIGINT UNSIGNED NOT NULL,
  companyId BIGINT UNSIGNED NOT NULL,
  ipAddress VARCHAR(45) NOT NULL,
  userAgent VARCHAR(255) NULL,
  sessionId VARCHAR(128) NOT NULL,
  sessionToken VARCHAR(128) NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  lastActivityAt DATETIME NOT NULL,
  expiresAt DATETIME NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revokedAt DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_userSession_sessionId (sessionId),
  KEY idx_userSession_companyId (companyId),
  KEY idx_userSession_userId (userId),
  KEY idx_userSession_isActive (isActive),
  KEY idx_userSession_lastActivityAt (lastActivityAt),
  CONSTRAINT fk_userSession_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_userSession_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== CLIENTS / CONTACTS ==========
CREATE TABLE IF NOT EXISTS Client (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(255) NULL,
  chantierRef VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  siret VARCHAR(32) NULL,
  accountingCustomerAccount VARCHAR(32) NULL,
  notes TEXT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_client_companyId (companyId),
  KEY idx_client_name (companyId, name),
  CONSTRAINT fk_client_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Contact (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  clientId BIGINT UNSIGNED NOT NULL,
  firstName VARCHAR(100) NULL,
  lastName VARCHAR(100) NULL,
  functionLabel VARCHAR(150) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  notes TEXT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_companyId (companyId),
  KEY idx_contact_clientId (companyId, clientId),
  CONSTRAINT fk_contact_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_contact_client
    FOREIGN KEY (clientId) REFERENCES Client (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== QUOTES ==========
CREATE TABLE IF NOT EXISTS Quote (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  clientId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NULL,
  quoteNumber VARCHAR(50) NULL,
  title VARCHAR(255) NULL,
  status ENUM('brouillon','envoye','a_relancer','accepte','refuse','annule') NOT NULL DEFAULT 'brouillon',
  followUpAt DATETIME NULL,
  createdByUserId BIGINT UNSIGNED NULL,
  sentAt DATETIME NULL,
  acceptedAt DATETIME NULL,
  refusedAt DATETIME NULL,
  notes TEXT NULL,
  proofFilePath VARCHAR(512) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_quote_companyId (companyId),
  KEY idx_quote_clientId (companyId, clientId),
  KEY idx_quote_projectId (companyId, projectId),
  KEY idx_quote_status (companyId, status),
  CONSTRAINT fk_quote_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_quote_client
    FOREIGN KEY (clientId) REFERENCES Client (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS QuoteItem (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  quoteId BIGINT UNSIGNED NOT NULL,
  priceLibraryItemId BIGINT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unitPrice DECIMAL(15,2) NOT NULL DEFAULT 0,
  lineTotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  vatRate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  revenueAccount VARCHAR(32) NULL,
  lineVat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  lineTtc DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  estimatedTimeMinutes INT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_quoteItem_companyId (companyId),
  KEY idx_quoteItem_quoteId (companyId, quoteId),
  CONSTRAINT fk_quoteItem_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_quoteItem_quote
    FOREIGN KEY (quoteId) REFERENCES Quote (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== INVOICES / PAYMENTS / PROJECTS (demandés plus tard) ==========
-- Pour respecter le planning, on ajoute seulement les tables “utilisables” dès maintenant
-- (auth + CRM + devis). Les autres entités seront ajoutées dans des migrations/DDL
-- lors des phases suivantes.

-- ================= INVOICES =================
CREATE TABLE IF NOT EXISTS Invoice (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  quoteId BIGINT UNSIGNED NULL,
  projectId BIGINT UNSIGNED NULL,
  clientId BIGINT UNSIGNED NOT NULL,
  invoiceNumber VARCHAR(50) NULL,
  title VARCHAR(255) NULL,
  dueDate DATE NOT NULL,
  status ENUM('brouillon','envoyee','partiellement_payee','payee','echue','annulee') NOT NULL DEFAULT 'brouillon',
  amountTotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  amountPaid DECIMAL(15,2) NOT NULL DEFAULT 0,
  createdByUserId BIGINT UNSIGNED NULL,
  sentAt DATETIME NULL,
  paidAt DATETIME NULL,
  notes TEXT NULL,
  accountingExportedAt DATETIME NULL,
  paymentToken VARCHAR(64) NULL,
  stripeCheckoutSessionId VARCHAR(255) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_invoice_companyId (companyId),
  UNIQUE KEY uq_invoice_paymentToken (paymentToken),
  KEY idx_invoice_clientId (companyId, clientId),
  KEY idx_invoice_quoteId (companyId, quoteId),
  KEY idx_invoice_projectId (companyId, projectId),
  KEY idx_invoice_status (companyId, status),
  CONSTRAINT fk_invoice_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_invoice_client
    FOREIGN KEY (clientId) REFERENCES Client (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_invoice_quote
    FOREIGN KEY (quoteId) REFERENCES Quote (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS InvoiceItem (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  invoiceId BIGINT UNSIGNED NOT NULL,
  priceLibraryItemId BIGINT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unitPrice DECIMAL(15,2) NOT NULL DEFAULT 0,
  lineTotal DECIMAL(15,2) NOT NULL DEFAULT 0,
  vatRate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  revenueAccount VARCHAR(32) NULL,
  lineVat DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  lineTtc DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  lineSort INT NOT NULL DEFAULT 0,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_invoiceItem_company (companyId),
  KEY idx_invoiceItem_invoice (companyId, invoiceId),
  CONSTRAINT fk_invoiceItem_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_invoiceItem_invoice
    FOREIGN KEY (invoiceId) REFERENCES Invoice (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= PAYMENTS =================
CREATE TABLE IF NOT EXISTS Payment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  invoiceId BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(100) NULL,
  reference VARCHAR(150) NULL,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
  status ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
  paidAt DATETIME NULL,
  metadata TEXT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payment_companyId (companyId),
  KEY idx_payment_invoiceId (companyId, invoiceId),
  KEY idx_payment_status (companyId, status),
  CONSTRAINT fk_payment_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_payment_invoice
    FOREIGN KEY (invoiceId) REFERENCES Invoice (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= PROJECTS / CHANTIERS =================
CREATE TABLE IF NOT EXISTS Project (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  clientId BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  status ENUM('planned','in_progress','completed','paused') NOT NULL DEFAULT 'planned',
  plannedStartDate DATE NULL,
  plannedEndDate DATE NULL,
  siteAddress VARCHAR(255) NULL,
  siteCity VARCHAR(150) NULL,
  sitePostalCode VARCHAR(20) NULL,
  actualStartDate DATE NULL,
  actualEndDate DATE NULL,
  notes TEXT NULL,
  createdByUserId BIGINT UNSIGNED NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_companyId (companyId),
  KEY idx_project_clientId (companyId, clientId),
  KEY idx_project_status (companyId, status),
  CONSTRAINT fk_project_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_project_client
    FOREIGN KEY (clientId) REFERENCES Client (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE Invoice
  ADD CONSTRAINT fk_invoice_project
  FOREIGN KEY (projectId) REFERENCES Project (id)
  ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS ProjectAssignment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NOT NULL,
  userId BIGINT UNSIGNED NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_projectAssignment (companyId, projectId, userId),
  KEY idx_projectAssignment_companyId (companyId),
  KEY idx_projectAssignment_projectId (companyId, projectId),
  KEY idx_projectAssignment_userId (companyId, userId),
  CONSTRAINT fk_projectAssignment_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projectAssignment_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projectAssignment_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= PLANNING / TASKS (squelette) =================
CREATE TABLE IF NOT EXISTS Task (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NULL,
  assignedUserId BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
  dueAt DATETIME NULL,
  createdByUserId BIGINT UNSIGNED NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_companyId (companyId),
  KEY idx_task_projectId (companyId, projectId),
  CONSTRAINT fk_task_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_task_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_task_assigned_user
    FOREIGN KEY (assignedUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_task_created_by
    FOREIGN KEY (createdByUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PlanningEntry (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NULL,
  taskId BIGINT UNSIGNED NULL,
  userId BIGINT UNSIGNED NULL,
  entryType ENUM('task','absence','meeting','other') NOT NULL DEFAULT 'task',
  title VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  startAt DATETIME NOT NULL,
  endAt DATETIME NOT NULL,
  createdByUserId BIGINT UNSIGNED NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_planning_companyId (companyId),
  KEY idx_planning_projectId (companyId, projectId),
  KEY idx_planning_userId (companyId, userId),
  KEY idx_planning_range (companyId, startAt, endAt),
  CONSTRAINT fk_planning_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_planning_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_planning_task
    FOREIGN KEY (taskId) REFERENCES Task (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_planning_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_planning_created_by
    FOREIGN KEY (createdByUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= PROJECT REPORTS / PHOTOS =================
CREATE TABLE IF NOT EXISTS ProjectReport (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NOT NULL,
  authorUserId BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projectReport_companyId (companyId),
  KEY idx_projectReport_projectId (companyId, projectId),
  CONSTRAINT fk_projectReport_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projectReport_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projectReport_author
    FOREIGN KEY (authorUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ProjectPhoto (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  projectId BIGINT UNSIGNED NOT NULL,
  uploaderUserId BIGINT UNSIGNED NULL,
  filePath VARCHAR(500) NOT NULL,
  caption VARCHAR(255) NULL,
  takenAt DATETIME NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projectPhoto_companyId (companyId),
  KEY idx_projectPhoto_projectId (companyId, projectId),
  CONSTRAINT fk_projectPhoto_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projectPhoto_project
    FOREIGN KEY (projectId) REFERENCES Project (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projectPhoto_uploader
    FOREIGN KEY (uploaderUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= HR: LEAVE REQUESTS =================
CREATE TABLE IF NOT EXISTS LeaveRequest (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  userId BIGINT UNSIGNED NOT NULL,
  type ENUM('conges','absence') NOT NULL DEFAULT 'conges',
  startDate DATE NOT NULL,
  endDate DATE NOT NULL,
  reason TEXT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  approvedByUserId BIGINT UNSIGNED NULL,
  approvedAt DATETIME NULL,
  rejectionReason VARCHAR(255) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leaveRequest_companyId (companyId),
  KEY idx_leaveRequest_userId (companyId, userId),
  KEY idx_leaveRequest_status (companyId, status),
  CONSTRAINT fk_leaveRequest_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_leaveRequest_user
    FOREIGN KEY (userId) REFERENCES `User` (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_leaveRequest_approvedBy
    FOREIGN KEY (approvedByUserId) REFERENCES `User` (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= PRICE LIBRARY =================
CREATE TABLE IF NOT EXISTS PriceCategory (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  defaultVatRate DECIMAL(5,2) NULL,
  defaultRevenueAccount VARCHAR(32) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_priceCategory_company (companyId),
  KEY idx_priceCategory_status (companyId, status),
  KEY idx_priceCategory_name (companyId, name),
  CONSTRAINT fk_priceCategory_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PriceLibraryItem (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  code VARCHAR(100) NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  unitLabel VARCHAR(50) NULL,
  unitPrice DECIMAL(15,2) NOT NULL DEFAULT 0,
  categoryId BIGINT UNSIGNED NULL,
  defaultVatRate DECIMAL(5,2) NULL,
  defaultRevenueAccount VARCHAR(32) NULL,
  estimatedTimeMinutes INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_priceLibrary_companyId (companyId),
  KEY idx_priceLibrary_status (companyId, status),
  KEY idx_priceLibrary_name (companyId, name),
  KEY idx_priceLibrary_category (companyId, categoryId),
  CONSTRAINT fk_priceLibrary_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_priceLibrary_category
    FOREIGN KEY (categoryId) REFERENCES PriceCategory (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= AUDIT (plateforme) =================
CREATE TABLE IF NOT EXISTS AuditLog (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  companyId BIGINT UNSIGNED NOT NULL,
  actorUserId BIGINT UNSIGNED NOT NULL,
  action VARCHAR(120) NOT NULL,
  targetCompanyId BIGINT UNSIGNED NULL,
  metadata TEXT NULL,
  ipAddress VARCHAR(45) NOT NULL,
  userAgent VARCHAR(255) NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_company (companyId),
  KEY idx_audit_actor (actorUserId),
  KEY idx_audit_created (createdAt),
  CONSTRAINT fk_audit_company
    FOREIGN KEY (companyId) REFERENCES Company (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_audit_actor
    FOREIGN KEY (actorUserId) REFERENCES `User` (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_audit_target
    FOREIGN KEY (targetCompanyId) REFERENCES Company (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

