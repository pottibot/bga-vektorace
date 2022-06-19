
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Vektoracenew implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- add column to track players turn order and change it when necessary
ALTER TABLE `player` 
ADD `player_turn_position` TINYINT UNSIGNED NOT NULL,
ADD `player_current_gear` TINYINT UNSIGNED NOT NULL DEFAULT 0,
ADD `player_tire_tokens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
ADD `player_nitro_tokens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
ADD `player_curve_number` TINYINT UNSIGNED NOT NULL DEFAULT 1,
ADD `player_curve_zone` TINYINT UNSIGNED NOT NULL DEFAULT 1,
ADD `player_lap_number` TINYINT UNSIGNED NOT NULL DEFAULT 0,
ADD `player_last_travel` TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- table that tracks table elments (literally on the table), their positions and orientation
-- probably name should be singular as is more formally correct
-- proprieties:
--  - entity, describes what kind of element it is. useful condition to check when working with all table elements, to apply different operation to the different entities (es. how do i display each element?)
--  - id, identifies an element in his entity class (actually id would be enought to identify each element). for cars it is the player id to which the car belongs to; for curves it is the number of that curve; for the pitwall it has the default value 0
--  - pos_x, pos_y indicates the coordinates of the scrollable map plane where all elements are positioned. unlike javascript, the plane coordinates grow in the traditional way (x: left to right, y: down to up, as opposed to y: up to down in js).
--    the coordinates are described with integers to be already usable as pixel values by js. by default a octagon is size 100px
--  - orientation, describes the orientation of the element as the k factor of k * pi/4. es table elements can only have orientations that are multiple of 45deg. (0 points right, 1 top-rigth, 2 top and so on)
CREATE TABLE IF NOT EXISTS `game_element` (
    `entity` VARCHAR(16) NOT NULL,
    `id` INT(8) UNSIGNED NOT NULL,
    `pos_x` FLOAT(24),
    `pos_y` FLOAT(24),
    `orientation` TINYINT(1),
    PRIMARY KEY (`entity`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- table that tracks what penalities or game modifiers are assigned to each player during the game, along with additional parameters needed to identify the precise effect of that penality/modifier.
-- all penalities and modifiers whould be reset after round end (with few exceptions)
-- below, a list of all possible penalities and modifiers whith explanations:
--      NAME
--  *   NoBlackMov      // (after emergency brake) player cannot perform 'black moves', that is, not using tire tokens to unlock special positions and orientations for the car placement. he may still spend tire tokens to decellerate (shift gear down more than 1 step) during future gear declaration
--  *   NoShiftDown     // (after suffering push attack) player cannot shift gear down during future gear declaration
--  *   NoShiftUp       // (after emergency brake) player cannot shift gear up during future gear declaration
--  *   CarStop         // (when emergency brake has no valid gear vectors) actually used by interface only
--  *   NoAttackMov     // (after emergency brake and give way) player cannot perform any attack maneuvers at the end of his movement phase
--  *   NoDrafting      // (after spending a tire token for either car or vector placement) player cannot perform drafting attacks at the end of his movement phase (he may still perform a shunt attack)
--  *   DeniedSideLeft  // (after suffering shunting from left side) player cannot select left positions for car and vector positioning
--  *   DeniedSideRight // (after suffering shunting from right side) player cannot select right positions for car and vector positioning
--  *   BoxBox          // (player declares intention to enter pit box) player is immune from attacks, can't attack either, can't use the boost and MUST transit through the pit area

CREATE TABLE IF NOT EXISTS `penalities_and_modifiers` (
    `player` INT(8) UNSIGNED NOT NULL,
    `NoShiftDown` BIT NOT NULL DEFAULT 0,
    `NoShiftUp` BIT NOT NULL DEFAULT 0,
    `CarStop` BIT NOT NULL DEFAULT 0,
    `NoAttackMov` BIT NOT NULL DEFAULT 0,
    `NoDrafting` BIT NOT NULL DEFAULT 0,
    `DeniedSideLeft` BIT NOT NULL DEFAULT 0,
    `DeniedSideRight` BIT NOT NULL DEFAULT 0,
    `ForceBoxGear` BIT NOT NULL DEFAULT 0,
    `BoxBox` BIT DEFAULT NULL,
    PRIMARY KEY (`player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- removed `FinishedRace` BIT NOT NULL DEFAULT 0,
-- removed `NoBlackMov` BIT NOT NULL DEFAULT 0,