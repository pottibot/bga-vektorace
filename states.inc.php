<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * VektoRace implementation : © <Pietro Luigi Porcedda> <pietro.l.porcedda@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */
 
$machinestates = array(

    // INITIAL STATE (DO NOT MODIFY)
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => 2 )
    ),

    // CAR POSITIONING
    // "flying-start" initial positioning phase.
    // each player place it's F8 racing car on the starting line, using the "Flying-Start" octagon to choose the proper position
    2 => array(
        "name" => "playerPositioning",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must choose a starting position'),
        "descriptionmyturn" => clienttranslate('${you} must choose a starting position'),
        "possibleactions" => array( "selectPosition" ),
        "args" => "argPlayerPositioning",
        "transitions" => array( "" => 3)
    ),

    // NEXT POSITIONING
    // control state where game checks if all player have placed their car, if so, procedes with the "Green light" event, which sets the start of the game
    3 => array(
        "name" => "nextPositioning",
        "type" => "game",
        "description" => "",
        "action" => "stNextPositioning",
        "transitions" => array( "nextPlayer" => 2, "greenLight" => 4)
    ),

    // GREEN LIGHT
    // first player choose starting gear vector for all the players on the first turn 
    4 => array(
        "name" => "greenLight",
        "type" => "activeplayer",
        "description" => clienttranslate('As pole position player, ${actplayer} must choose the starting gear vector for all players'),
        "descriptionmyturn" => clienttranslate('As pole position player, ${you} must choose the starting gear vector for all players'),
        "possibleactions" => array( "chooseStartingGear" ),
        "transitions" => array( "" => 5)
    ),

    // PLACE VECTOR
    // active player choose how to place his choosen gear vector
    5 => array(
        "name" => "placeVector",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must place their previously declared gear vector'),
        "descriptionmyturn" => clienttranslate('${you} must place your previously declared gear vector'),
        "args" => "argPossibleVectorPositions",
        "possibleactions" => array("placeVector"),
        "transitions" => array( "moveCar" => 6, "useBoost" => 7)
    ),

    // MOVE CAR
    // after having placed the vector, the active player chooses how to position his car at the end of the vector
    6 => array(
        "name" => "moveCar",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must move their car in the desired position and orientation'),
        "descriptionmyturn" => clienttranslate('${you} must move your car in the desired position and orientation'),
        "args" => "argPossibleCarPositions",
        "possibleactions" => array( ""),
        "transitions" => array( "attack" => 8, "endMovement" => 9, "pitStop" => 13, "victory" => 15),
        "updateGameProgression" => true
    ),

    // USE BOOST
    // after having placed the vector (and before placing the car) the player might choose to extend his movment by using some boost vector (and spending a nitro token).
    7 => array(
        "name" => "useBoost",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must choose which boost vector to use'),
        "descriptionmyturn" => clienttranslate('${you} must choose which boost vector to use'),
        "args" => "argBoostPosition",
        "possibleactions" => array( "placeBoost"),
        "transitions" => array( "moveCar" => 6)
    ),

    // ATTACK MANEUVERS
    // after having the car for this turn, if possible, the player can choose to attack the closest car in some way
    8 => array(
        "name" => "attackManeuvers",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must choose which attack maneuvers to perform'),
        "descriptionmyturn" => clienttranslate('${you} must choose which attack maneuvers to perform'),
        "args" => "", // need args?
        "possibleactions" => array("swapPaint"), // tradurre: sportellata, bussata, sorpasso in scia,
        "transitions" => array( "endMovement" => 9, "pitStop" => 13, "victory" => 15)
    ),

    // FUTURE GEAR DECLARATION
    // at the end of his turn, the player must declare what gear he will use the next turn
    9 => array(
        "name" => "futureGearDeclaration",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must state what gear vector they will use in the next turn'),
        "descriptionmyturn" => clienttranslate('${you} must state what gear vector you will use in the next turn'),
        "args" => "argCurrentGear",
        "possibleactions" => array("declareGear"),
        "transitions" => array("" => 10)
    ),

    // NEXT PLAYER TURN
    // control state where game checks if all player have moved their car for this round, if so it produces a new turn order based on the car standings position.
    // before jumping to the next player turn, it also checks if that player would be obstructed by a player in front, if so it jumps to yield turn state
    10 => array(
        "name" => "nextPlayer",
        "type" => "game",
        "action" => "nextPlayerOrNextRound",
        "transitions" => array( "" => 5)
    ),

    // YIELD TURN
    // player with an obstructing car in front (but whom's turn order is behind) might want to yield his turn to that player so to have more space to moove in during his moovement (happens rarely, during sharp turns)
    11 => array(
        "name" => "yieldTurn",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must choose whether to yield their turn to ${frontPlayer} as it might obstruct their maneuvers (they will be playing just after)'),
        "descriptionmyturn" => clienttranslate('${you} must choose whether to yield your turn to ${frontPlayer} as it might obstruct your maneuvers (you will be playing just after)'),
        "args" => "argTokensAvailability",
        "possibleactions" => array( "chooseTokens" ),
        "transitions" => array( "" => 9)
    ),

    // PIT STOP
    // a player which enters the pit stop area can collect tire and nitro tokens (minus eventual penalities)
    13 => array(
        "name" => "pitStop",
        "type" => "activeplayer",
        "description" => clienttranslate('${actplayer} must select ${x} tokens'),
        "descriptionmyturn" => clienttranslate('${you} must select ${x} tokens'),
        "args" => "argTokensAvailability",
        "possibleactions" => array( "chooseTokens" ),
        "transitions" => array( "" => 9)
    ),

    // STATE STRUCTURE:
    /*  n => array(
     *      "name" => "",
     *      "type" => "",
     *      "description" => clienttranslate('${actplayer} ...'),
     *      "descriptionmyturn" => clienttranslate('${you} ...'),
     *      "action" => "",
     *      "args" => "",
     *      "possibleactions" => array( ""),
     *      "transitions" => array( "" => )
     *      "updateGameProgression" => true/false,
     *  ),
     */    
   
    
    // FINAL STATE (DO NOT MODIFY)
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )

);



