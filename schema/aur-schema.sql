-- The MySQL database layout for the AUR.  Certain data
-- is also included such as AccountTypes, etc.
--
DROP DATABASE IF EXISTS AUR;
CREATE DATABASE AUR DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE AUR;

-- Define the Account Types for the AUR.
--
CREATE TABLE AccountTypes (
	ID TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
	AccountType VARCHAR(32) NOT NULL DEFAULT '',
	PRIMARY KEY (ID)
) ENGINE = InnoDB;
INSERT INTO AccountTypes (ID, AccountType) VALUES (1, 'User');
INSERT INTO AccountTypes (ID, AccountType) VALUES (2, 'Trusted User');
INSERT INTO AccountTypes (ID, AccountType) VALUES (3, 'Developer');
INSERT INTO AccountTypes (ID, AccountType) VALUES (4, 'Trusted User & Developer');


-- User information for each user regardless of type.
--
CREATE TABLE Users (
	ID INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
	AccountTypeID TINYINT UNSIGNED NOT NULL DEFAULT 1,
	Suspended TINYINT UNSIGNED NOT NULL DEFAULT 0,
	Username VARCHAR(32) NOT NULL,
	Email VARCHAR(64) NOT NULL,
	Passwd CHAR(32) NOT NULL,
	Salt CHAR(32) NOT NULL DEFAULT '',
	ResetKey CHAR(32) NOT NULL DEFAULT '',
	RealName VARCHAR(64) NOT NULL DEFAULT '',
	LangPreference VARCHAR(5) NOT NULL DEFAULT 'en',
	IRCNick VARCHAR(32) NOT NULL DEFAULT '',
	PGPKey VARCHAR(40) NULL DEFAULT NULL,
	LastLogin BIGINT UNSIGNED NOT NULL DEFAULT 0,
	LastLoginIPAddress INTEGER UNSIGNED NOT NULL DEFAULT 0,
	InactivityTS BIGINT UNSIGNED NOT NULL DEFAULT 0,
	RegistrationTS TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (ID),
	UNIQUE (Username),
	UNIQUE (Email),
	INDEX (AccountTypeID),
	FOREIGN KEY (AccountTypeID) REFERENCES AccountTypes(ID) ON DELETE NO ACTION
) ENGINE = InnoDB;
-- A default developer account for testing purposes
INSERT INTO Users (ID, AccountTypeID, Username, Email, Passwd) VALUES (
	1, 3, 'dev', 'dev@localhost', MD5('dev'));
INSERT INTO Users (ID, AccountTypeID, Username, Email, Passwd) VALUES (
	2, 2, 'tu', 'tu@localhost', MD5('tu'));
INSERT INTO Users (ID, AccountTypeID, Username, Email, Passwd) VALUES (
	3, 1, 'user', 'user@localhost', MD5('user'));


-- SSH public keys used for the aurweb SSH/Git interface.
--
CREATE TABLE SSHPubKeys (
	UserID INTEGER UNSIGNED NOT NULL,
	Fingerprint VARCHAR(44) NOT NULL,
	PubKey VARCHAR(4096) NOT NULL,
	PRIMARY KEY (Fingerprint),
	FOREIGN KEY (UserID) REFERENCES Users(ID) ON DELETE CASCADE
) ENGINE = InnoDB;


-- Track Users logging in/out of AUR web site.
--
CREATE TABLE Sessions (
	UsersID INTEGER UNSIGNED NOT NULL,
	SessionID CHAR(32) NOT NULL,
	LastUpdateTS BIGINT UNSIGNED NOT NULL,
	FOREIGN KEY (UsersID) REFERENCES Users(ID) ON DELETE CASCADE,
	UNIQUE (SessionID)
) ENGINE = InnoDB;


-- Information on package bases
--
CREATE TABLE PackageBases (
	ID INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
	Name VARCHAR(255) NOT NULL,
	NumVotes INTEGER UNSIGNED NOT NULL DEFAULT 0,
	Popularity DECIMAL(10,6) UNSIGNED NOT NULL DEFAULT 0,
	OutOfDateTS BIGINT UNSIGNED NULL DEFAULT NULL,
	SubmittedTS BIGINT UNSIGNED NOT NULL,
	ModifiedTS BIGINT UNSIGNED NOT NULL,
	SubmitterUID INTEGER UNSIGNED NULL DEFAULT NULL,     -- who submitted it?
	MaintainerUID INTEGER UNSIGNED NULL DEFAULT NULL,    -- User
	PackagerUID INTEGER UNSIGNED NULL DEFAULT NULL,      -- Last packager
	PRIMARY KEY (ID),
	UNIQUE (Name),
	INDEX (NumVotes),
	INDEX (SubmitterUID),
	INDEX (MaintainerUID),
	INDEX (PackagerUID),
	-- deleting a user will cause packages to be orphaned, not deleted
	FOREIGN KEY (SubmitterUID) REFERENCES Users(ID) ON DELETE SET NULL,
	FOREIGN KEY (MaintainerUID) REFERENCES Users(ID) ON DELETE SET NULL,
	FOREIGN KEY (PackagerUID) REFERENCES Users(ID) ON DELETE SET NULL
) ENGINE = InnoDB;


-- Keywords of package bases
--
CREATE TABLE PackageKeywords (
	PackageBaseID INTEGER UNSIGNED NOT NULL,
	Keyword VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (PackageBaseID, Keyword),
	FOREIGN KEY (PackageBaseID) REFERENCES PackageBases(ID) ON DELETE CASCADE
) ENGINE = InnoDB;


-- Information about the actual packages
--
CREATE TABLE Packages (
	ID INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
	PackageBaseID INTEGER UNSIGNED NOT NULL,
	Name VARCHAR(255) NOT NULL,
	Version VARCHAR(255) NOT NULL DEFAULT '',
	Description VARCHAR(255) NULL DEFAULT NULL,
	URL VARCHAR(255) NULL DEFAULT NULL,
	PRIMARY KEY (ID),
	UNIQUE (Name),
	FOREIGN KEY (PackageBaseID) REFERENCES PackageBases(ID) ON DELETE CASCADE
) ENGINE = InnoDB;


-- Information about licenses
--
CREATE TABLE Licenses (
	ID INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
	Name VARCHAR(255) NOT NULL,
	PRIMARY KEY (ID),
	UNIQUE (Name)
) ENGINE = InnoDB;


-- Information about package-license-relations
--
CREATE TABLE PackageLicenses (
	PackageID INTEGER UNSIGNED NOT NULL,
	LicenseID INTEGER UNSIGNED NOT NULL,
	PRIMARY KEY (PackageID, LicenseID),
	FOREIGN KEY (PackageID) REFERENCES Packages(ID) ON DELETE CASCADE,
	FOREIGN KEY (LicenseID) REFERENCES Licenses(ID) ON DELETE CASCADE
) ENGINE = InnoDB;


-- Information about groups
--
CREATE TABLE Groups (
	ID INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
	Name VARCHAR(255) NOT NULL,
	PRIMARY KEY (ID),
	UNIQUE (Name)
) ENGINE = InnoDB;


-- Information about package-group-relations
--
CREATE TABLE PackageGroups (
	PackageID INTEGER UNSIGNED NOT NULL,
	GroupID INTEGER UNSIGNED NOT NULL,
	PRIMARY KEY (PackageID, GroupID),
	FOREIGN KEY (PackageID) REFERENCES Packages(ID) ON DELETE CASCADE,
	FOREIGN KEY (GroupID) REFERENCES Groups(ID) ON DELETE CASCADE
) ENGINE = InnoDB;


-- Define the package dependency types
--
CREATE TABLE DependencyTypes (
	ID TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
	Name VARCHAR(32) NOT NULL DEFAULT '',
	PRIMARY KEY (ID)
) ENGINE = InnoDB;
INSERT INTO DependencyTypes VALUES (1, 'depends');
INSERT INTO DependencyTypes VALUES (2, 'makedepends');
INSERT INTO DependencyTypes VALUES (3, 'checkdepends');
INSERT INTO DependencyTypes VALUES (4, 'optdepends');


-- Track which dependencies a package has
--
CREATE TABLE PackageDepends (
	PackageID INTEGER UNSIGNED NOT NULL,
	DepTypeID TINYINT UNSIGNED NOT NULL,
	DepName VARCHAR(255) NOT NULL,
	DepCondition VARCHAR(255),
	DepArch VARCHAR(255) NULL DEFAULT NULL,
	INDEX (PackageID),
	INDEX (DepName),
	FOREIGN KEY (PackageID) REFERENCES Packages(ID) ON DELETE CASCADE,
	FOREIGN KEY (DepTypeID) REFERENCES DependencyTypes(ID) ON DELETE NO ACTION
) ENGINE = InnoDB;


-- Define the package relation types
--
CREATE TABLE RelationTypes (
	ID TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
	Name VARCHAR(32) NOT NULL DEFAULT '',
	PRIMARY KEY (ID)
) ENGINE = InnoDB;
INSERT INTO RelationTypes VALUES (1, 'conflicts');
INSERT INTO RelationTypes VALUES (2, 'provides');
INSERT INTO RelationTypes VALUES (3, 'replaces');


-- Track which conflicts, provides and replaces a package has
--
CREATE TABLE PackageRelations (
	PackageID INTEGER UNSIGNED NOT NULL,
	RelTypeID TINYINT UNSIGNED NOT NULL,
	RelName VARCHAR(255) NOT NULL,
	RelCondition VARCHAR(255),
	RelArch VARCHAR(255) NULL DEFAULT NULL,
	INDEX (PackageID),
	INDEX (RelName),
	FOREIGN KEY (PackageID) REFERENCES Packages(ID) ON DELETE CASCADE,
	FOREIGN KEY (RelTypeID) REFERENCES RelationTypes(ID) ON DELETE NO ACTION
) ENGINE = InnoDB;


-- Track which sources a package has
--
CREATE TABLE PackageSources (
	PackageID INTEGER UNSIGNED NOT NULL,
	Source VARCHAR(255) NOT NULL DEFAULT "/dev/null",
	SourceArch VARCHAR(255) NULL DEFAULT NULL,
	INDEX (PackageID),
	FOREIGN KEY (PackageID) REFERENCES Packages(ID) ON DELETE CASCADE
) ENGINE = InnoDB;


-- Track votes for packages
--
CREATE TABLE PackageVotes (
	UsersID INTEGER UNSIGNED NOT NULL,
	PackageBaseID INTEGER UNSIGNED NOT NULL,
	VoteTS BIGINT UNSIGNED NULL DEFAULT NULL,
	INDEX (UsersID),
	INDEX (PackageBaseID),
	FOREIGN KEY (UsersID) REFERENCES Users(ID) ON DELETE CASCADE,
	FOREIGN KEY (PackageBaseID) REFERENCES PackageBases(ID) ON DELETE CASCADE
) ENGINE = InnoDB;
CREATE UNIQUE INDEX VoteUsersIDPackageID ON PackageVotes (UsersID, PackageBaseID);

-- Record comments for packages
--
CREATE TABLE PackageComments (
	ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	PackageBaseID INTEGER UNSIGNED NOT NULL,
	UsersID INTEGER UNSIGNED NULL DEFAULT NULL,
	Comments TEXT NOT NULL DEFAULT '',
	CommentTS BIGINT UNSIGNED NOT NULL DEFAULT 0,
	DelUsersID INTEGER UNSIGNED NULL DEFAULT NULL,
	PRIMARY KEY (ID),
	INDEX (UsersID),
	INDEX (PackageBaseID),
	FOREIGN KEY (UsersID) REFERENCES Users(ID) ON DELETE SET NULL,
	FOREIGN KEY (DelUsersID) REFERENCES Users(ID) ON DELETE CASCADE,
	FOREIGN KEY (PackageBaseID) REFERENCES PackageBases(ID) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Package base co-maintainers
--
CREATE TABLE PackageComaintainers (
	UsersID INTEGER UNSIGNED NOT NULL,
	PackageBaseID INTEGER UNSIGNED NOT NULL,
	Priority INTEGER UNSIGNED NOT NULL,
	INDEX (UsersID),
	INDEX (PackageBaseID),
	FOREIGN KEY (UsersID) REFERENCES Users(ID) ON DELETE CASCADE,
	FOREIGN KEY (PackageBaseID) REFERENCES PackageBases(ID) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Comment addition notifications
--
CREATE TABLE CommentNotify (
	PackageBaseID INTEGER UNSIGNED NOT NULL,
	UserID INTEGER UNSIGNED NOT NULL,
	FOREIGN KEY (PackageBaseID) REFERENCES PackageBases(ID) ON DELETE CASCADE,
	FOREIGN KEY (UserID) REFERENCES Users(ID) ON DELETE CASCADE
) ENGINE = InnoDB;
CREATE UNIQUE INDEX NotifyUserIDPkgID ON CommentNotify (UserID, PackageBaseID);

-- Package name blacklist
--
CREATE TABLE PackageBlacklist (
	ID INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
	Name VARCHAR(64) NOT NULL,
	PRIMARY KEY (ID),
	UNIQUE (Name)
) ENGINE = InnoDB;

-- Define package request types
--
CREATE TABLE RequestTypes (
	ID TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
	Name VARCHAR(32) NOT NULL DEFAULT '',
	PRIMARY KEY (ID)
) ENGINE = InnoDB;
INSERT INTO RequestTypes VALUES (1, 'deletion');
INSERT INTO RequestTypes VALUES (2, 'orphan');
INSERT INTO RequestTypes VALUES (3, 'merge');

-- Package requests
--
CREATE TABLE PackageRequests (
	ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	ReqTypeID TINYINT UNSIGNED NOT NULL,
	PackageBaseID INTEGER UNSIGNED NULL,
	PackageBaseName VARCHAR(255) NOT NULL,
	MergeBaseName VARCHAR(255) NULL,
	UsersID INTEGER UNSIGNED NULL DEFAULT NULL,
	Comments TEXT NOT NULL DEFAULT '',
	RequestTS BIGINT UNSIGNED NOT NULL DEFAULT 0,
	Status TINYINT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (ID),
	INDEX (UsersID),
	INDEX (PackageBaseID),
	FOREIGN KEY (ReqTypeID) REFERENCES RequestTypes(ID) ON DELETE NO ACTION,
	FOREIGN KEY (UsersID) REFERENCES Users(ID) ON DELETE SET NULL,
	FOREIGN KEY (PackageBaseID) REFERENCES PackageBases(ID) ON DELETE SET NULL
) ENGINE = InnoDB;

-- Vote information
--
CREATE TABLE IF NOT EXISTS TU_VoteInfo (
	ID int(10) unsigned NOT NULL auto_increment,
	Agenda text NOT NULL,
	User VARCHAR(32) NOT NULL,
	Submitted bigint(20) unsigned NOT NULL,
	End bigint(20) unsigned NOT NULL,
	Quorum decimal(2, 2) unsigned NOT NULL,
	SubmitterID int(10) unsigned NOT NULL,
	Yes tinyint(3) unsigned NOT NULL default '0',
	No tinyint(3) unsigned NOT NULL default '0',
	Abstain tinyint(3) unsigned NOT NULL default '0',
	ActiveTUs tinyint(3) unsigned NOT NULL default '0',
	PRIMARY KEY  (ID),
	FOREIGN KEY (SubmitterID) REFERENCES Users(ID) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Individual vote records
--
CREATE TABLE IF NOT EXISTS TU_Votes (
	VoteID int(10) unsigned NOT NULL,
	UserID int(10) unsigned NOT NULL,
	FOREIGN KEY (VoteID) REFERENCES TU_VoteInfo(ID) ON DELETE CASCADE,
	FOREIGN KEY (UserID) REFERENCES Users(ID) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Malicious user banning
--
CREATE TABLE Bans (
	IPAddress INTEGER UNSIGNED NOT NULL DEFAULT 0,
	BanTS TIMESTAMP NOT NULL,
	PRIMARY KEY (IPAddress)
) ENGINE = InnoDB;
