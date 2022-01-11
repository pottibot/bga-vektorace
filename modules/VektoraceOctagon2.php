<?php

require_once('VektoracePoint.php');

class VektoraceOctagon extends VektoraceGameElement {

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
        for ($i=0; $i<8; $i++) { 
         
            $c = clone $this->center;
            $c->translateVec(self::getOctProperties()['radius'], (2*$i+1) * M_PI/8);
            $vertices[$i] = $c;
        }

        // rotate all points to face oct dir
        $the = ($this->direction - 4) * M_PI_4;
        foreach ($vertices as &$p) {
            $p->changeRefPlane($this->center);
            $p->rotate($the);
            $p->translate($this->center->x(),$this->center->y());
        } unset($p);
        
        return $vertices;
    }

    public function collidesWith(VektoraceGameElement $el) {

        $err = 1;

        // compute distance between elements centers
        $distance = VektoracePoint::distance($this->center,$el->center);

        // check if elements are almost completely overlapping (centers match -> distance 0, collision certain)
        if ($distance < $err*2) return true;

        // check if element is an octagon, if so, if distance is grater than their sixes combined, element certainly won't collide (too far apart) 
        if (is_a($el,'VektoraceOctagon') && $distance > 2*self::getOctProperties()['radius']) return false;

        return self::SATcollision($this->getVertices(), $el->getVertices(), $err);
    }
}