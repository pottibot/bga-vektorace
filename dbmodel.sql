
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- VektoRace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- add column to track players turn order and change it when necessary
ALTER TABLE `player` 
ADD `player_turn_position` TINYINT UNSIGNED NOT NULL;

-- table that tracks table elments (literally on the table), their positions and orientation
-- probably name should be singular as is more formally correct
-- proprieties:
--  - entity, describes what kind of element it is. useful condition to check when working with all table elements, to apply different operation to the different entities (es. how do i display each element?)
--  - id, identifies an element in his entity class (actually id would be enought to identify each element). for cars it is the player id to which the car belongs to; for curves it is the number of that curve; for the pitwall it has the default value 0
--  - pos_x, pos_y indicates the coordinates of the scrollable map plane where all elements are positioned. unlike javascript, the plane coordinates grow in the traditional way (x: left to right, y: down to up, as opposed to y: up to down in js).
--    the coordinates are described with integers to be already usable as pixel values by js. by default a octagon is size 100px
--  - orientation, describes the orientation of the element as the k factor of k * pi/4. es table elements can only have orientations that are multiple of 45deg. (0 points right, 1 top-rigth, 2 top and so on)
    `entity` VARCHAR(16) NOT NULL,
    `id` INT(8) UNSIGNED NOT NULL,
    `pos_x` INT(4),
    `pos_y` INT(4),
    `orientation` TINYINT(1),
    PRIMARY KEY (`entity`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- to delete
CREATE TABLE IF NOT EXISTS `octagon_sizes` (
    `propriety` VARCHAR(16) NOT NULL,
    `val` DECIMAL(10,3),
    PRIMARY KEY (`propriety`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;