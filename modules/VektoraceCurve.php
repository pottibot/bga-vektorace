<?php

require_once('VektoracePoint.php');
require_once('VektoraceOctagon.php');

Class VektoraceCurve extends VektoraceOctagon {

    public function getVertices() {

        $octMeasures = self::getOctProperties();   

        // same as for octagon here
        $ret = array();
        for ($i=0; $i<8; $i++) { 
         
            $c = clone $this->center;
            $c->translateVec(self::getOctProperties()['radius'], (2*$i+1) * M_PI/8);
            $ret[$i] = $c;
        }

        // now slice array and generate 5 and 6 as translation of already generated points
        //      2  *  1 
        //    *       * *
        //  3         6   0
        //  *      *      *
        //  4 * 5         7
        //    *         *
        //      5  *  6             

        $ret[5] = clone $ret[4];
        $ret[6] = clone $ret[1];

        $ret[5]->translate($octMeasures['side'],0);
        $ret[6]->translate(0,-$octMeasures['side']);

        $ret = array_slice($ret, 1, 6);

        // same as for octagon here
        $the = ($this->direction - 3) * M_PI_4;
        foreach ($ret as &$p) {
            $p->changeRefPlane($this->center);
            $p->rotate($the);
            $p->translate($this->center->x(),$this->center->y());
        }
        unset($p);

        // finally translate alla points to match real center
        $ro = $octMeasures['size']/2 - ($octMeasures['side']+$octMeasures['corner_segment'])/2;
        $ro *= sqrt(2); // actually need diagonal of displacement 'square'
        $the = $this->direction * M_PI_4;

        foreach ($ret as &$p) {
            $p->translateVec(-$ro,$the);
        } unset($p);
        
        return $ret;
    }

    public function collidesWith(VektoraceGameElement $el) {
        return self::SATcollision($this->getVertices(), $el->getVertices(), 1);
    }
}