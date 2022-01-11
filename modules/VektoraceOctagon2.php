<?php

require_once('VektoracePoint2.php');

class VektoraceOctagon2 extends VektoraceGameElement {

    // generate vertices using a translation vector pointing to each vertex by turn
    // vector magnitude is radius of octagon
    // vector rotation is k*PI/4 + PI/8
    //      2  *  1 
    //    *         *
    //  3             0
    //  *      *      *
    //  4             7
    //    *         *
    //      5  *  6    
    // return vertices in an orderly manner, indipendently of octagon rotation. this will be helpfull when we need to refer to a precise vertext without knowing the octagon rotation
    public function getVertices() {           

        $vertices = array();
        for ($i=0; $i<8; $i++)
            $vertices[$i] = $this->center->translateVec(self::getOctProperties()['radius'], (2*$i+1) * M_PI/8);

        // rotate all points to face oct dir
        $the = ($this->direction - 4) * M_PI_4;
        foreach ($vertices as &$p)
            $p = $p->scaleAndRotateFromOrigin($this->center,1,1,$the);
        unset($p);
        
        return $vertices;
    }

    // detect collision between octagon and any other game element
    public function collidesWith(VektoraceGameElement $el, $consider = 'whole', $err = 1) {

        // if element is an octagon check distance, if grater than double their radius, element certainly won't collide (too far apart)
        if (is_a($el,'VektoraceOctagon2') && VektoracePoint2::distance($this->center,$el->center) > 2*self::getOctProperties()['radius']) return false;

        $thisPoli = $this->getVertices();
        $elPoli = $el->getVertices();

        if (is_a($el,'VektoraceVector2') || is_a($el,'VektoracePitwall')) {

            if (self::SATcollision($thisPoli, $elPoli[0], $err)) return true;
            if (self::SATcollision($thisPoli, $elPoli[1], $err)) return true;
            if (self::SATcollision($thisPoli, $elPoli[2], $err)) return true;
            return false;

        } else return self::SATcollision($thisPoli, $elPoli, $err);
    }
}