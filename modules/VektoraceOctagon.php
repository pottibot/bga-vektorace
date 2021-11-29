<?php

require_once('VektoracePoint.php');

// classe used to handle all octagons operation and measurments
class VektoraceOctagon {
    // size (in pixels, to represent a more direct conversion for js) of the box that inscribes the octagon, orizontal diameter
    // implicitly defines the scale of all generated octagons
    private static $size = 100;

    // octagon center coordinates as VektoracePoint
    private $center;

    // octagon elememt orientation (where is it facing, es. the car) [positive integer between 0 and 7]
    private $direction;

    private $isCurve;

    public function __construct(VektoracePoint $center, $direction=4, $isCurve=false) {

        $this->center = clone $center;

        if ($direction<0 || $direction>7) throw new Exception("Invalid 'direction' argument. Value must be between 0 and 7", 1);       
        $this->direction = $direction;

        $this->isCurve = $isCurve;
    }

    public function __clone() {
        $this->center = clone $this->center;
    }

    public function __toString() {
        return '[center: '.$this->center.', direction: '.$this->direction.']';
    }

    public function getCenter() {
        return clone $this->center;
    }

    public function isCurve() {
        return $this->isCurve;
    }

    public function getDirection() {
        return $this->direction;
    }
    
    // returns all useful measures when dealing with octagons
    public static function getOctProperties() {
        $sidlen = self::$size / (1 + 2/sqrt(2)); // length of all equal sides of the octagon
        $cseg = $sidlen / sqrt(2); // half the length of the segment resulting from size - side. or the cathetus of the rectangular triangle built on the angle of the box which inscribes the octagon.
        $radius = sqrt(pow(self::$size/2,2) + pow($sidlen/2,2)); // radius of the circle that inscribes the octagon. or the distance between the center and its furthest apexes

        return array("size" => self::$size,
                     "side" => $sidlen,
                     "corner_segment" => $cseg,
                     "radius" => $radius);
    }

    // returns a list containing the center points of the $amount adjacent octagons, symmetric to the facing direction 
    // direction order is the same used to describe the game elements orientation in the database (counter clockwise, as $dir * PI/4)

    // REDO USING VECTORS
    public function getAdjacentOctagons(int $amount, $inverseDir=false) {

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
        for ($i=0; $i < $amount; $i++) {

            $c = clone $this->center;
            $c->translateVec(self::$size, (($key+$i)%8) * M_PI/4);
            $ret[] = $c;
        }

        return (count($ret)==1)? $ret[0] : $ret;
    }

    // given a direction (same as before) it generates all possible flying-start position
    // which means finding the adiecent 3 octagons in that direction and do the same for these returned octagons in the respective direction
    public function flyingStartPositions() {

        $behind_3 = $this->getAdjacentOctagons(3,true);

        $right_3 = new VektoraceOctagon($behind_3[2], ($this->getDirection()+1 +8)%8);
        $right_3 = $right_3->getAdjacentOctagons(3,true);

        $center_3 = new VektoraceOctagon($behind_3[1], $this->getDirection());
        $center_3 = $center_3->getAdjacentOctagons(3,true);

        $left_3 = new VektoraceOctagon($behind_3[0], ($this->getDirection()-1 +8)%8);
        $left_3 = $left_3->getAdjacentOctagons(3,true);

        return array_unique(array_merge(array_merge($right_3,$center_3),$left_3), SORT_REGULAR);
    }

    // returns array of all vertices of $this octagon. if $isCurve is true, return vertices in the shape of a curve, pointing in $this->direction (shown below)
    public function getVertices() {
        // get all useful proprieties to calculate the position of all vertices
        $octMeasures = self::getOctProperties();

        // compose array of vertices in a orderly manner (key = (K-1)/2 of K * PI/8. inversely: K = key*2 + 1)
        //      2  *  1 
        //    *       * *
        //  3         6   0
        //  *      *      *
        //  4 * 5         7
        //    *         *
        //      5  *  6    
        //             

        $ret = array();
        for ($i=0; $i<8; $i++) { 
         
            $c = clone $this->center;
            $c->translateVec($octMeasures['radius'], (2*$i+1) * M_PI/8);
            $ret[$i] = $c;
        }

        if ($this->isCurve) {

            $ret[5] = clone $ret[4];
            $ret[6] = clone $ret[1];

            $ret[5]->translate($octMeasures['side'],0);
            $ret[6]->translate(0,-$octMeasures['side']);

            $ret = array_slice($ret, 1, 6);
        }

        // rotate all points to face vec dir
        $omg = ($this->direction - (($this->isCurve)? 3 : 4)) * M_PI_4; // 3 and 4 are standard orientation for curve and octagon respectively (due to how curves and cars are oriented in the image sprites)
        foreach ($ret as &$p) {
            $p->changeRefPlane($this->center);
            $p->rotate($omg);
            $p->translate($this->center->x(),$this->center->y());
        }
        unset($p);

        if($this->isCurve) {
            $ro = $octMeasures['size']/2 - ($octMeasures['side']+$octMeasures['corner_segment'])/2;
            $ro *= sqrt(2); // actually need diagonal of displacement 'square'
            $omg = $this->direction * M_PI_4;

            foreach ($ret as &$p) {
                $p->translateVec(-$ro,$omg);
            } unset($p);
        }
        
        return $ret;
    }
    
    // returns true if $this and $oct collide (uses SAT algo)
    public function collidesWith(VektoraceOctagon $oct, $carOnly = false) {

        $err = 1; // rounding error to exclude proximal collisions

        // compute distance between octagons centers
        $distance = VektoracePoint::distance($this->center,$oct->center);
        
        if ($distance < $err*2) return true; // elements basically overlapping

        // if it's a simple octagon and the distance is less then the size of the octagon itself, collision is assured
        if (!$this->isCurve && !$carOnly && $distance < self::$size-($err*2)) return true;

        // run sat algo only if distance is less then the octagons radius, thus surrounding circles intersects. 
        if ($distance < 2*self::getOctProperties()['radius']) {

            $oct1 = $this->getVertices();
            if (!$this->isCurve && $carOnly) $oct1 = array($oct1[0], $oct1[3], $oct1[4], $oct1[7]);

            $oct2 = $oct->getVertices();

            // if a separating axis exists on the standard plane, octagons arn't colliding
            if (self::findSeparatingAxis($oct1, $oct2, $err)) return false;
            
            // else, rotate plane 45deg and check there
            $omg = M_PI_4;

            foreach ($oct1 as $i => &$vertex) {
                $vertex->rotate($omg);
            }
            unset($vertex);

            foreach ($oct2 as $i => &$vertex) {
                $vertex->rotate($omg);
            }
            unset($vertex);

            // if it finally finds a separating axis, then the octagons don't collide (return false), otherwise they do (return true)
            return !self::findSeparatingAxis($oct1, $oct2, $err);
            
        } else return false;
    }

    // method takes two arrays of points as sets of vertices of a polygon
    // and returns true if a separating axis exists between them in their standard refernce plane
    // (to check other planes, rotate points and repeat)
    public static function findSeparatingAxis($poli1, $poli2, $err = 0) {
        
        // extract all x and y coordinates to find extremes
        $xsP1 = [];
        $ysP1 = [];
        $xsP2 = [];
        $ysP2 = [];

        foreach ($poli1 as $key => $vertex) {
            $xsP1[] = $vertex->x();
            $ysP1[] = $vertex->y();
        }

        foreach ($poli2 as $key => $vertex) {
            $xsP2[] = $vertex->x();
            $ysP2[] = $vertex->y();
        }

        $maxX1 = max($xsP1);
        $minX1 = min($xsP1);
        $maxX2 = max($xsP2);
        $minX2 = min($xsP2);

        // if intervals defined by the respective extremes (for the x coordinates) don't overlap, a separating axis exists
        // THERE MUST BE A SIMPLER WAY TO DO THIS
        if (!( // if it does not happen that
                ($maxX2 < $maxX1-$err && $maxX2 > $minX1+$err) || // the max of the 2nd poly is contained within the range of the 1st poly or
                ($minX2 < $maxX1-$err && $minX2 > $minX1+$err) || // the min of the 2nd poly is contained within the range of the 1st poly or
                ($minX2 < $minX1-$err && $maxX2 > $maxX1-$err)) // the min of the 2nd poly is smaller than the min of the 1st poly and vice versa the max of the 2nd poly is bigger than the max of the 1st poly
            ) 
            return true;

        // else check y coordinates
        $maxY1 = max($ysP1);
        $minY1 = min($ysP1);
        $maxY2 = max($ysP2);
        $minY2 = min($ysP2);

        // (as before, but for the y)
        // finally, if a intervals don't overlap for the y-axis, a separating axis exists (return true).
        // otherways no separating axis has been found (return false)
        return !(($maxY2 < $maxY1-$err && $maxY2 > $minY1+$err) || ($minY2 < $maxY1-$err && $minY2 > $minY1+$err) || ($minY2 < $minY1-$err && $maxY2 > $maxY1-$err));


    }

    // detects collition between $this octagon and a vector object (basically analize vector as three different shapes, two octagons and a simple rectangle)
    public function collidesWithVector(VektoraceVector $vector, $carOnly = false) {

        // OCTAGON COLLIDES WITH EITHER THE TOP OR BOTTOM VECTOR'S OCTAGON

        if ($this->collidesWith($vector->getBottomOct(), $carOnly) || $this->collidesWith($vector->getTopOct(), $carOnly)) return true;

        // OCTAGON COLLIDES WITH THE VECTOR'S INNER RECTANGLE
        
        $vectorInnerRect = $vector->innerRectVertices();
        $thisOct = $this->getVertices();
        if ($carOnly) $thisOct = array($thisOct[0], $thisOct[3], $thisOct[4], $thisOct[7]);

        $omg = M_PI_4;

        if (self::findSeparatingAxis($vectorInnerRect, $thisOct)) return false;

        foreach ($vectorInnerRect as &$vertex) {
            $vertex->rotate($omg);
        }
        unset($vertex);

        foreach ($thisOct as &$vertex) {
            $vertex->rotate($omg);
        }
        unset($vertex);

        return !self::findSeparatingAxis($vectorInnerRect, $thisOct);
    }

    // returns norm VektoracePoint "mathematic" vector that points in the direction where car is pointing, along with its origin (useful for other methods) the midpoint of its front edge
    public function getDirectionNorm() {

        $octVs = $this->getVertices();

        // find midpoint between them from which the norm originates
        $m = VektoracePoint::midpoint($octVs[3], $octVs[4]);

        // calculate norm vector
        $n = VektoracePoint::displacementVector($m, $this->center);
        $n->invert();
        $n->normalize();

        return array( 'norm' => clone $n, 'origin' => $m); // origin is midpoint of front edge
    }

    // returns true if $this octagon is behing $oct, according to the line defined by the front-facing edge of $oct (towards its $direction)
    // the idea is to find the norm of this front-facing edge and see if the dot product with each vertex of $this octagon results in negative (thus together they form an angle greater than 90deg, which means the vertex is behind that edge)
    public function isBehind(VektoraceOctagon $oct) {

        ['norm'=>$n, 'origin'=>$m] = $oct->getDirectionNorm();

        $thisCar = $this->getVertices();
        $thisCar = array($thisCar[3],$thisCar[4],$thisCar[7],$thisCar[0]);

        // for each vertex of $this, find vector from m to the vertex and calculate dotproduct between them
        foreach ($thisCar as $key => $vertex) {
            $v = VektoracePoint::displacementVector($m, $vertex);
            $v->normalize();

            if (VektoracePoint::dot($n, $v) >= -0.1) return false;
        }

        return true;
    }

    // determines if $this car new positions is sufficent to overtake $other car which is (presumibly) in front of.
    // according to game rules:
    // if (this) car (and NOT its wider octaogn base), IS NOT behind the nose line of the other car
    // and the other car IS behind the nose line of this car
    // then the car overtakes the one in front.
    // otherwise, if one of the two condition is not verified, the car doesn't overtake the one in front and keeps its previous position.
    // it might sound confusing but if you look at how the isBehind method is implemented you can understand how one car might be simultaneously in front and of and behind another car.
    public function overtake(VektoraceOctagon $other) {

        return !$this->isBehind($other) && $other->isBehind($this);
    }
}