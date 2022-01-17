<?php

require_once('VektoraceGameElement.php');

Class VektoraceCurve extends VektoraceGameElement {

    public function getVertices() {

        $octMeasures = self::getOctagonMeasures();   

        // same as for octagon here
        $ret = array();
        for ($i=0; $i<8; $i++)
            $ret[$i] = $this->center->translatePolar(self::getOctagonMeasures()['radius'], (2*$i+1) * M_PI/8);

        // now slice array and generate 5 and 6 as translation of already generated points
        //      2  *  1 
        //    *       * *
        //  3         6   0
        //  *      x      *
        //  4 * 5         7
        //    *         *
        //      5  *  6             

        $ret[5] = $ret[4]->translate($octMeasures['side'],0);
        $ret[6] = $ret[1]->translate(0,-$octMeasures['side']);

        $ret = array_slice($ret, 1, 6);

        // same as for octagon here
        $the = ($this->direction - 3) * M_PI_4;
        foreach ($ret as &$p)
            $p = $p->transformFromOrigin($this->center,1,1,$the);
        unset($p);

        // finally translate alla points to match real center
        $ro = $octMeasures['size']/2 - ($octMeasures['side']+$octMeasures['corner'])/2;
        $ro *= sqrt(2); // actually need diagonal of displacement 'square'
        $the = ($this->direction-4) * M_PI_4;

        foreach ($ret as &$p)
            $p = $p->translatePolar($ro,$the);
        unset($p);
        
        return $ret;
    }
}