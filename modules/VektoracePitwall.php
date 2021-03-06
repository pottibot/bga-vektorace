<?php

require_once('VektoraceVector.php');

// not a singleton
class VektoracePitwall extends VektoraceVector {

    public function __construct(VektoracePoint $center, int $direction) {

        parent::__construct($center, $direction, 4);
    }

    public function getVertices() {
        $pitwallPolys = parent::getVertices();

        foreach ($pitwallPolys as &$poly) {
            foreach ($poly as &$v) {
                $v = $v->transformFromOrigin($this->center, 0.75, 0.75, 0);
            } unset($v);
        } unset($poly);

        return $pitwallPolys;
    }

    public function getProperties() {

        // useful properties to divide area around pitwall as follows
        //
        //                    |                    (a)                    |
        //                    *  *  *              /|\              *  *  *    
        //                  * |       *             |             *       | *  
        //                *   |         * * * * * * * * * * * * *         |   *
        //   --- (c)  <-- *--(Q)-x-----------------(O)-----------------x-(P)--* --> (b)---
        //                *   |         * * * * * * * * * * * * *         |   *
        //                  * |       *                           *       | *
        //                    *  *  *                               *  *  *      
        //                    |                                           |

        $dir = $this->direction;

        $O = $this->center;

        $top = $this->topOct->getCenter();
        $bot = $this->bottomOct->getCenter();

        // find Q and P (translated points of top and bot to match pitbox entrance and exit)
        $ro = self::getOctagonMeasures()['side']/2;
        $the = $dir * M_PI_4;

        $Q = $top->translatePolar($ro, $the);
        $Q = $Q->transformFromOrigin($O,0.75,0.75);

        $P = $bot->translatePolar($ro, $the-M_PI);
        $P = $P->transformFromOrigin($O,0.75,0.75);

        
        // norm vector pointing upward in respect to a layed down pitwall (dir 4)
        $a = VektoracePoint::createPolarVector(1, ($dir-2) * M_PI_4);

        // norm vector pointing opposite of pw dir
        $b = VektoracePoint::createPolarVector(1, ($dir-4) * M_PI_4);

        // norm vector pointing same as pw dir
        $c = VektoracePoint::createPolarVector(1, $dir * M_PI_4);

        return [
            'O' => $O,
            'P' => $P,
            'Q' => $Q,
            'a' => $a,
            'b' => $b,
            'c' => $c
        ];
    }

}