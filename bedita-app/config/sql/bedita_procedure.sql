--- Procedure e funzioni per l abero
DROP PROCEDURE  IF EXISTS appendChildTree ;
delimiter //
CREATE PROCEDURE appendChildTree (_ID INT, _IDParent INT)
DETERMINISTIC
BEGIN
DECLARE pathParent MEDIUMTEXT DEFAULT '' ;
DECLARE pathID MEDIUMTEXT DEFAULT '' ;
DECLARE _priority INT  ;

SET pathParent  = (SELECT path FROM trees WHERE id = _IDParent) ;
SET pathID  	= IF(pathParent IS NULL, CONCAT('/', _ID), CONCAT(pathParent, '/', _ID)) ;
SET pathParent 	= IF(pathParent IS NULL, '/', pathParent) ;
SET _priority  	= (SELECT (MAX(priority)+1) FROM trees WHERE parent_id = _IDParent) ;
SET _priority  	= IF(_priority IS NULL, 1, _priority) ;

INSERT INTO `trees` ( `id` , `parent_id` , `path` , `parent_path` , `priority` ) VALUES (_ID, _IDParent , pathID, pathParent , _priority) ;

END
//
delimiter ;


DROP PROCEDURE  IF EXISTS moveChildTreeUp ;
delimiter //
CREATE PROCEDURE moveChildTreeUp (_ID INT, _IDParent INT)
DETERMINISTIC
BEGIN
DECLARE _priority INT ;
DECLARE _minPriority INT ;
DECLARE _pathParent MEDIUMTEXT ;

SET _pathParent  	= (SELECT path FROM trees WHERE id = _IDParent) ;
SET _priority  	 	= (SELECT priority FROM trees WHERE id = _ID AND parent_id) ;
SET _minPriority  	= (SELECT MIN(priority) FROM trees WHERE id = _ID AND parent_id) ;

IF  _priority > _minPriority THEN
	BEGIN
	 UPDATE trees SET priority = _priority WHERE parent_path = _pathParent AND priority = (_priority - 1) ;
	 UPDATE trees SET priority = (_priority - 1) WHERE id = _ID AND parent_id = _IDParent ;
	 END ;
END IF ;

END
//
delimiter ;


DROP PROCEDURE  IF EXISTS moveChildTreeDown ;
delimiter //
CREATE PROCEDURE moveChildTreeDown (_ID INT, _IDParent INT)
DETERMINISTIC
BEGIN
DECLARE _priority INT ;
DECLARE _maxPriority INT ;
DECLARE _pathParent MEDIUMTEXT ;

SET _pathParent  	= (SELECT path FROM trees WHERE id = _IDParent) ;
SET _priority  	 	= (SELECT priority FROM trees WHERE id = _ID AND parent_id) ;
SET _maxPriority  	= (SELECT MAX(priority) FROM trees WHERE id = _ID AND parent_id) ;

IF  _priority < _maxPriority THEN
	BEGIN
	 UPDATE trees SET priority = _priority WHERE parent_path = _pathParent AND priority = (_priority + 1) ;
	 UPDATE trees SET priority = (_priority + 1) WHERE id = _ID AND parent_id = _IDParent ;
	 END ;
END IF ;

END
//
delimiter ;


DROP PROCEDURE  IF EXISTS moveChildTreeFirst ;
delimiter //
CREATE PROCEDURE moveChildTreeFirst (_ID INT, _IDParent INT)
DETERMINISTIC
BEGIN
DECLARE done INT DEFAULT 0;
DECLARE _priority INT ;
DECLARE _idCurr INT ;
DECLARE curs CURSOR FOR SELECT id, priority FROM trees WHERE parent_id = _IDParent ORDER BY priority ;
DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET done = 1;

OPEN curs;

REPEAT
	FETCH curs INTO _idCurr, _priority ;

	IF NOT done THEN
		IF _idCurr = _ID THEN
			UPDATE trees SET priority = 1 WHERE id = _ID AND parent_id = _IDParent ;
			SET done = 1 ;
		ELSE
			UPDATE trees SET priority = (_priority+1) WHERE id = _idCurr AND parent_id = _IDParent ;
		END IF ;
	END IF;
UNTIL done END REPEAT;
END
//
delimiter ;

DROP PROCEDURE  IF EXISTS moveChildTreeLast ;
delimiter //
CREATE PROCEDURE moveChildTreeLast (_ID INT, _IDParent INT)
DETERMINISTIC
BEGIN
DECLARE done INT DEFAULT 0;
DECLARE _priority INT ;
DECLARE _maxPriority INT ;
DECLARE _idCurr INT ;
DECLARE curs CURSOR FOR SELECT id, priority FROM trees WHERE parent_id = _IDParent ORDER BY priority DESC ;
DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET done = 1;

SET _maxPriority  	= (SELECT MAX(priority) FROM trees WHERE id = _ID AND parent_id) ;

OPEN curs;

REPEAT
	FETCH curs INTO _idCurr, _priority ;

	IF NOT done THEN
		IF _idCurr = _ID THEN
			UPDATE trees SET priority = _maxPriority WHERE id = _ID AND parent_id = _IDParent ;
			SET done = 1 ;
		ELSE
			UPDATE trees SET priority = (_priority-1) WHERE id = _idCurr AND parent_id = _IDParent ;
		END IF ;
	END IF;
UNTIL done END REPEAT;
END
//
delimiter ;

DROP PROCEDURE  IF EXISTS switchChildTree ;
delimiter //
CREATE PROCEDURE switchChildTree (_ID INT, _IDParent INT, _PRIOR INT)
DETERMINISTIC
BEGIN
DECLARE done INT DEFAULT 0;
DECLARE _priority INT ;
DECLARE _old_priority INT ;


DECLARE _maxPriority INT ;

SET _maxPriority  	= (SELECT MAX(priority) FROM trees WHERE parent_id = _IDParent) ;
SET _priority		= IF(_PRIOR > _maxPriority, _maxPriority, _PRIOR) ;
SET _old_priority	= (SELECT priority FROM trees WHERE id = _ID AND parent_id = _IDParent) ;

UPDATE trees SET priority = _old_priority
WHERE parent_id = _IDParent  AND priority = _priority ;

UPDATE trees SET priority = _priority  WHERE id = _ID AND parent_id = _IDParent ;
END
//
delimiter ;

DROP PROCEDURE  IF EXISTS moveTree ;
delimiter //
CREATE PROCEDURE moveTree (_ID INT, _IDOldParent INT, _IDNewParent INT)
DETERMINISTIC
BEGIN
DECLARE done INT DEFAULT 0;
DECLARE _oldPath MEDIUMTEXT ;
DECLARE _newPath MEDIUMTEXT ;
DECLARE _oldPathParent MEDIUMTEXT ;
DECLARE _newPathParent MEDIUMTEXT ;

SET _oldPath 		= (SELECT path FROM trees WHERE id = _ID AND parent_id = _IDOldParent) ;
SET _oldPathParent 	= (SELECT parent_path FROM trees WHERE id = _ID AND parent_id = _IDOldParent) ;
SET _newPathParent 	= (SELECT path FROM trees WHERE id = _IDNewParent) ;
SET _newPath		= REPLACE(_oldPath, _oldPathParent, _newPathParent) ;

UPDATE trees SET path = _newPath, parent_path = _newPathParent, parent_id = _IDNewParent WHERE path LIKE _oldPath ;

UPDATE trees SET
path = REPLACE(path, _oldPath, _newPath) , parent_path = REPLACE(parent_path, _oldPath, _newPath)
WHERE path  LIKE CONCAT(_oldPath, '%') ;

END
//
delimiter ;

DROP FUNCTION  IF EXISTS isParentTree ;
delimiter //
CREATE FUNCTION isParentTree (_IDParent INT, _IDChild INT) RETURNS INT
DETERMINISTIC
BEGIN
DECLARE _pathParent MEDIUMTEXT ;
DECLARE ret INT ;

SET _pathParent = (SELECT path FROM trees WHERE id = _IDParent) ;
SET ret = IF((SELECT id FROM trees WHERE path LIKE CONCAT(_pathParent, '%') AND id = _IDChild) IS NULL, 0, 1) ;

RETURN ret ;

END
//
delimiter ;

DROP PROCEDURE  IF EXISTS cloneTree ;
delimiter //
CREATE PROCEDURE cloneTree (_ID INT, _IDOLD INT)
DETERMINISTIC
BEGIN
DECLARE done INT DEFAULT 0;
DECLARE _idparent INT ;
DECLARE curs CURSOR FOR SELECT parent_id FROM trees WHERE path  LIKE CONCAT('%/', _IDOLD) ;
DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET done = 1;

-- clona gli oggetti foglia
OPEN curs;
REPEAT
	FETCH curs INTO _idparent ;
	IF NOT done THEN
	 	CALL appendChildTree(_ID, _idparent) ;
	END IF;
UNTIL done END REPEAT;

-- clona le ramificazioni
INSERT INTO trees
SELECT
id,
_ID AS parent_id,
REPLACE(path, _IDOLD, _ID) AS path,
REPLACE(parent_path, _IDOLD, _ID) AS parent_path,
priority
FROM trees
WHERE
path LIKE CONCAT('%/', _IDOLD, '/%') ;

END
//
delimiter ;

-- ---------------------------------------------------
-- 	MODULE PERMISSIONS
-- ---------------------------------------------------

DROP PROCEDURE  IF EXISTS replacePermissionModule ;
delimiter //
CREATE PROCEDURE replacePermissionModule (_MDL VARCHAR(255), _USERGROUP VARCHAR(255), _SWITCH VARCHAR(40), _FLAG INT)
DETERMINISTIC
BEGIN

DECLARE _UGID INT DEFAULT 0;
DECLARE _idprm INT DEFAULT 0;

SET _UGID 	= IF(_SWITCH = 'user', (SELECT ID FROM users WHERE userid = _USERGROUP), (SELECT ID FROM groups WHERE name = _USERGROUP)) ;

IF _UGID > 0 THEN
	SET _idprm	= (SELECT id FROM permission_modules WHERE module_id = (SELECT id FROM modules WHERE name = _MDL)
	AND ugid = _UGID AND switch = _SWITCH) ;

	IF _idprm > 0 THEN
		UPDATE permission_modules SET flag = _FLAG WHERE id = _idprm ;
	ELSE
		INSERT permission_modules (module_id, ugid, switch, flag) VALUES ((SELECT id FROM modules WHERE name = _MDL) , _UGID, _SWITCH, _FLAG) ;
	END IF ;
END IF ;
END
//
delimiter ;

DROP PROCEDURE  IF EXISTS deletePermissionModule ;
delimiter //
CREATE PROCEDURE deletePermissionModule (_MDL VARCHAR(255), _USERGROUP VARCHAR(255), _SWITCH VARCHAR(40))
DETERMINISTIC
BEGIN

DECLARE _UGID INT DEFAULT 0;
DECLARE _idprm INT DEFAULT 0;

SET _UGID 	= IF(_SWITCH = 'user', (SELECT ID FROM users WHERE userid = _USERGROUP), (SELECT ID FROM groups WHERE name = _USERGROUP)) ;

IF _UGID > 0 THEN
	DELETE FROM permission_modules WHERE module_id = (SELECT id FROM modules WHERE name = _MDL)
	AND ugid = _UGID AND switch = _SWITCH ;
END IF ;
END
//
delimiter ;

DROP FUNCTION  IF EXISTS prmsModuleUserByID ;
delimiter //
CREATE FUNCTION prmsModuleUserByID (_USERID VARCHAR(40), _MDL VARCHAR(255), _PRMS INT) RETURNS INT
DETERMINISTIC
BEGIN
DECLARE prmsG INT DEFAULT 0 ;
DECLARE prmsU INT DEFAULT 0 ;

SET prmsG = (
	SELECT DISTINCT
	BIT_OR(permission_modules.flag & _PRMS) AS perms
	FROM
	permission_modules
	WHERE
	permission_modules.module_id = (SELECT id FROM modules WHERE path = _MDL)
	AND
	(
	permission_modules.ugid IN
		(
		SELECT groups_users.`group_id`
		FROM
		users INNER JOIN groups_users ON users.id = groups_users.user_id
		WHERE users.userid = _USERID
		)
	OR

	permission_modules.ugid =
		(
		SELECT id FROM groups WHERE name = 'guest'
		)
	)
	AND
	permission_modules.switch = 'group'
	AND
	(permission_modules.flag & _PRMS)
	) ;
SET prmsG  = IF(prmsG IS NULL, 0, prmsG) ;

SET prmsU  = (
	SELECT DISTINCT
	(permission_modules.flag & _PRMS) AS perms
	FROM
	permission_modules
	WHERE
	permission_modules.ugid =
	(
	SELECT id FROM users WHERE userid = _USERID
	)
	AND
	permission_modules.switch = 'user'
	AND
	(permission_modules.flag & _PRMS)
	AND
	permission_modules.module_id = (SELECT id FROM modules WHERE path = _MDL)
) ;
SET prmsU  = IF(prmsU IS NULL, 0, prmsU) ;


RETURN (prmsG|prmsU) ;

END
//
delimiter ;

DROP FUNCTION  IF EXISTS prmsModuleGroupByName ;
delimiter //
CREATE FUNCTION prmsModuleGroupByName (_GROUPNAME VARCHAR(40), _MDL VARCHAR(255), _PRMS INT) RETURNS INT
DETERMINISTIC
BEGIN
DECLARE prmsG INT DEFAULT 0 ;

SET prmsG = (
	SELECT DISTINCT
	BIT_OR(permission_modules.flag & _PRMS) AS perms
	FROM
	permission_modules
	WHERE
	permission_modules.module_id = (SELECT id FROM modules WHERE path = _MDL)
	AND
	(
	permission_modules.ugid =
		(
		SELECT id FROM groups WHERE name = _GROUPNAME
		)
	)
	AND
	permission_modules.switch = 'group'
	AND
	(permission_modules.flag & _PRMS)
	) ;
SET prmsG  = IF(prmsG IS NULL, 0, prmsG) ;

RETURN (prmsG) ;

END
//
delimiter ;
