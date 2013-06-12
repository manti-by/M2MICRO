DROP PROCEDURE IF EXISTS `GET_LATEST_IMAGES`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `GET_LATEST_IMAGES`(IN `_limit` int)
BEGIN
		SELECT *
		FROM `files`
		WHERE `type` = 'gallery'
		ORDER BY `timestamp` DESC, `id` DESC
		LIMIT _limit;
END
;;
DELIMITER ;