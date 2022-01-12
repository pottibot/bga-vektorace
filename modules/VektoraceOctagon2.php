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

        $thisPoly = $this->getVertices();
        $elPoly = $el->getVertices();

        if (is_a($elPoly[0],'VektoracePoint2')) return self::SATcollision($thisPoly, $elPoly, $err);
                else throw new Exception('Unrecognized polygon data structure');

        if (gettype($elPoly[0]) == 'array') {

            foreach ($elPoly as $polyComp) {
                if (is_a($polyComp[0],'VektoracePoint2')) {
                    if (self::SATcollision($thisPoly, $polyComp, $err)) return true;
                } else throw new Exception('Unrecognized polygon data structure');

            }
            return false;

        } else
            if (is_a($elPoly[0],'VektoracePoint2')) return self::SATcollision($thisPoly, $elPoly, $err);
            else throw new Exception('Unrecognized polygon data structure');
    }

    // returns a list containing the center points of the $amount adjacent octagons, symmetric to the facing direction 
    // direction order is the same used to describe the game elements orientation in the database (counter clockwise, as $dir * PI/4)
    public function getAdjacentOctagons(int $amount, $inverseDir=false) {

        // octacon facing direction interpreted as follows
        //
        //       *  2  * 
        //     5         1
        //   *             * 
        //   4      +      0  ->
        //   *             * 
        //     6         8
        //       *  7  *   
        //

        if ($amount<1 || $amount>8) {
            throw new Exception("Invalid amount argument, value must be between 1 and 8", 1);
        }

        // take direction, obtain key as a function of amount (shift so that direction is in the middle of the keys), mod the result to deal with the overflow of the clock
        $key = ($inverseDir)? (($this->direction - 4 + 8) % 8) : $this->direction; 
        $key -= floor(($amount-1)/2); // floor necessary only when key is not odd number (should not happen)
        $key += 8;

        $ret = array();

        // for amount times, extract one adjacent octagon center coordinates, put it in the returned array and repeat
        for ($i=0; $i < $amount; $i++) 
            $ret[] = $this->center->translateVec(self::$size, (($key+$i)%8) * M_PI/4);

        return (count($ret)==1)? $ret[0] : $ret; // if single value asked, single value returned, otherwise vector is returned
    }

    // returns normalized vector pointing towards octagon facing direction, along with midpoint of fron edge (origin of vector)
    public function getDirectionNorm() {

        $octVs = $this->getVertices();

        // find midpoint of the octagon front edge
        $m = VektoracePoint::midpoint($octVs[3], $octVs[4]);

        // calculate norm vector
        $n = VektoracePoint::displacementVector($m, $this->center)->invert()->normalize();

        return array( 'norm' => clone $n, 'origin' => $m); // origin is midpoint of front edge
    }

    // return true if this octagon is behind another octagon
    // to determine this, check if every vertex of this oct is behind the plane defined by the front edge of the other oct
    // that is, if the dot product of the vector pointing to the vertex and the norm vector of the front edge is negative.
    public function isBehind(VektoraceOctagon2 $oct) {

        ['norm'=>$n, 'origin'=>$m] = $oct->getDirectionNorm();

        $thisCar = $this->getVertices();
        $thisCar = array($thisCar[3],$thisCar[4],$thisCar[7],$thisCar[0]);

        // for each vertex of $this, find vector from m to the vertex and calculate dotproduct between them
        foreach ($thisCar as $vertex) {
            $v = VektoracePoint::displacementVector($m, $vertex)->normalize();

            if (VektoracePoint::dot($n, $v) >= -0.005) return false; // consider some error
        }

        return true;
    }

    // method to determine if an octagon overtakes another in the race order according to game rules
    // this overtakes other if it's both not behind other and other is behind this
    // (it can happen that neither octagon can be considered behind the other, for example when cars are pointing in towards each other. or again, that both car are behind each other, when cars are pointing in opposite directions)
    public function overtake(VektoraceOctagon2 $other) {

        return !$this->isBehind($other) && $other->isBehind($this);
    }

    // returns in which section of the area surrounding a curve does this octagon (center) fall
    // always use dot product and vertex vectors to do the checks
    public function curveZone(VektoraceCurve $curve) {

        $carVec = VektoracePoint::displacementVector($curve->getCenter(), $this->center)->normalize();

        // search each zone (divide curve area in 8 pies of PI/4 angle)
        for ($i=0; $i<8; $i++) {

            $the = (($curve->direction() - 4 - 0.5 - $i ) * M_PI_4); // start searching pie starting from behind the curve
            $zoneVec = new VektoracePoint2(); // vector pointing to pie center
            $zoneVec = $zoneVec->translateVec(1,$the);

            if (VektoracePoint2::dot($carVec, $zoneVec) >= cos(M_PI/8)) return $i; // if dot with pie vector is smaller than half of pie angle, then car center is in this zone
        }

        throw new Exception("Function shouldn't have reached this point");
    }

    // returns wether this octagon is inside a specific zone relative to the pitwall element
    // checks depends on what and how many vertices of the octagon are considered
    // see VektoracePitwall->getPitwallProperties() to understand how this zones are defined 
    public function inPitZone(VektoracePitwall $pw, $zone, $consider = 'nose') {

        $pwProps = $pw->getPitwallProperties();

        $vertices = $this->getVertices();
        if ($consider == 'nose') $vertices = [VektoracePoint::midpoint($vertices[3],$vertices[4])];
  
        $inside = 0;
        foreach ($vertices as $v) {

            $A = VektoracePoint::dot(
                $pwProps['a'],
                VektoracePoint::displacementVector($pwProps['O'], $v)
            ) > 0;

            $B = VektoracePoint::dot(
                $pwProps['b'],
                VektoracePoint::displacementVector($pwProps['P'], $v)
            ) > 0;

            $C = VektoracePoint::dot(
                $pwProps['c'],
                VektoracePoint::displacementVector($pwProps['Q'], $v)
            ) > 0;

            switch ($zone) {
                case 'grid': if ($A && !$B && !$C) $inside++;
                    break;
                
                case 'EoC': if ($A && $B) $inside++; // End of Circuit. CHANGE TO 'finish'
                    break;

                case 'entrance': if (!$A && $B) $inside++;
                    break;
                
                case 'box': if (!$A && !$B && !$C) $inside++;
                    break;
            
                case 'exit': if (!$A && $C) $inside++;
                    break;
                
                case 'SoC': if ($A && $C) $inside++; // Start of Circuit. CHANGE TO 'start'
                    break;
            }
        }

        switch ($consider) {
            case 'nose':
                return $inside == 1;
                break;
            
            case 'whole':
                return $inside == 8;
                break;

            case 'any':
                return $inside > 0;
                break;
        }
    }

    // return new position for when car get penality of overshooting the box entrance
    // simply move position to match the end of the pitbox area
    // if returned position is not valid, specify getDefault = true to position the car in a standard, valid position
    public function boxOvershootPenality($pw, $getDefault = false) {

        $pwProps = $pw->getPitwallProperties();

        if ($getDefault) {
            $newPos = $pwProps['Q']->translateVec(self::getOctProperties()['size'], ($dir+2) * M_PI_4);
            $newPos = $newPos->translateVec((self::getOctProperties()['size']/2)+1, $dir * M_PI_4 + M_PI);

            return $newPos;

        } else {
            // calc overshoot using distance to vector plane formula (same as old method to find distance to lightsource)
            $v = $this->getCenter();

            $c_dot_v = VektoracePoint::dot(
                $pwProps['c'],
                VektoracePoint::displacementVector($pwProps['Q'], $v)
            );
            $mag_v = VektoracePoint::distance($pwProps['Q'], $v);

            $overshoot = ($c_dot_v / $mag_v) + self::getOctProperties()['size']/2 +1;

            $newPos = $this->getCenter()->translateVec($overshoot, $dir * M_PI_4 + M_PI);

            return $newPos;
        }
    }
}