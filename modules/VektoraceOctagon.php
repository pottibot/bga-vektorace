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
    public function getAdjacentOctagons(int $amount) {

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

        // temp variables to use short names in the extraction of adjacent octagon positions
        $s = self::$size;
        $u = self::getOctProperties()["side"];
        $x = $this->center->x();
        $y = $this->center->y();

        // take direction, obtain key as a function of amount (shift so that direction is in the middle of the keys), mod the result to deal with the overflow of the clock
        $key = $this->direction; 
        $key -= floor(($amount-1)/2); // floor necessary only when key is not odd number
        $key += 8;

        $ret = array();

        // for amount times, extract one adjacent octagon center coordinates, put it in the returned array and repeat
        for ($i=0; $i < $amount; $i++) {
            
            // depending on adjacency direction, the coordinates are results of different operations
            switch ($key%8) {
                
                case 0: $ret[0] = new VektoracePoint($x+$s, $y);
                        break;

                case 1: $ret[1] = new VektoracePoint($x+$u/2+$s/2, $y+$u/2+$s/2);
                        break;

                case 2: $ret[2] = new VektoracePoint($x, $y+$s);
                        break;
                        
                case 3: $ret[3] = new VektoracePoint($x-$u/2-$s/2, $y+$u/2+$s/2);
                        break;
                        
                case 4: $ret[4] = new VektoracePoint($x-$s, $y);
                        break;
                        
                case 5: $ret[5] = new VektoracePoint($x-$u/2-$s/2, $y-$u/2-$s/2);
                        break;
                        
                case 6: $ret[6] = new VektoracePoint($x, $y-$s);
                        break;
                        
                case 7: $ret[7] = new VektoracePoint($x+$u/2+$s/2, $y-$u/2-$s/2);
                        break;
                
                default: $ret[$key%8] = $key%8;
            }

            // increase key to extract next position
            $key++;
        }

        return $ret;
    }

    // given a direction (same as before) it generates all possible flying-start position
    // which means finding the adiecent 3 octagons in that direction and do the same for these returned octagons in the respective direction
    public function flyingStartPositions() {

        $ret = array();

        $this->direction = ($this->direction-4+8)%8; // invert direction to extract position at the back of the car (thus opposite to where it's pointing)

        // extract 'flying start' positions
        $fs = $this->getAdjacentOctagons(3);

        $this->direction = ($this->direction+4+8)%8; // invert again once positions are extracted

        // from these, extract 3 position each, pointing in the direction they were generated on 
        foreach ($fs as $dir => $pos) {
            $oct = new VektoraceOctagon($pos, $dir);
            $ret[] = array_values($oct->getAdjacentOctagons(3)); // we can lose the direction indexing now, we want a pure array
        }

        // merge all in one single array
        return array_unique(array_merge(array_merge($ret[0],$ret[1]),$ret[2]), SORT_REGULAR);
    }

    // returns array of all vertices of $this octagon. if $isCurve is true, return vertices in the shape of a curve, pointing in $this->direction (shown below)
    // curve collision gives error:
    //Fatal error: Uncaught Error: Cannot unpack array with string keys in /var/tournoi/release/games/vektorace/999999-9999/modules/VektoraceOctagon.php:174 Stack trace: #0 /var/tournoi/release/games/vektorace/999999-9999/modules/VektoraceOctagon.php(197): VektoraceOctagon->getVertices(true) #1 /var/tournoi/release/games/vektorace/999999-9999/vektorace.game.php(210): VektoraceOctagon->collidesWith(Object(VektoraceOctagon), true, '0') #2 /var/tournoi/release/games/vektorace/999999-9999/vektorace.game.php(402): VektoRace->detectCollision(Object(VektoraceOctagon)) #3 /var/tournoi/release/tournoi-210922-1031-gs/www/game/module/table/gamestate.game.php(634): VektoRace->argPlayerPositioning() #4 /var/tournoi/release/tournoi-210922-1031-gs/www/game/module/table/gamestate.game.php(129): Gamestate->loadStateArgs() #5 /var/tournoi/release/tournoi-210922-1031-gs/www/game/module/table/gamestate.game.php(393): Gamestate->state() #6 /var/tournoi/release/tournoi-210922-1031-gs/www/game/module/table/gamestate.game.php(365): Gamestate->jumpToSt in /var/tournoi/release/games/vektorace/999999-9999/modules/VektoraceOctagon.php on line 174

    // WARNING: DESCRIBES VERTICES AS JS WOULD (Y AXIS INVERTED; STARTING FROM TOP LEFT CORNER), MAYBE CHANGE THAT 
    public function getVertices() {
        // get all useful proprieties to calculate the position of all vertices
        $octMeasures = self::getOctProperties();
        $siz = $octMeasures['size'];
        $sid = $octMeasures['side'];
        $seg = $octMeasures['corner_segment'];

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

            $ret[5]->translate($sid,0);
            $ret[6]->translate(0,-$sid);

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
            $ms = self::getOctProperties();
            $ro = $ms['size']/2 - ($ms['side']+$ms['corner_segment'])/2;
            $ro *= sqrt(2); // actually need diagonal of displacement 'square'
            $omg = $this->direction * M_PI_4;

            foreach ($ret as &$p) {
                $p->translateVec(-$ro,$omg);
            } unset($p);
        }
        
        return $ret;
    }

    public function getDirectionNorm() {
        // i have to extract the vertices that define the edge facing $direction

        // remember, vertices are sorted as such: key = (K-1)/2 of K * PI/8. inversely: K = key*2 + 1)
        //   2  1  
        // 3      0
        // 4      7
        //   5  6      

        // since the orientation of and octagon is indicated with the k-th pi/angle 
        // we canb extract p1 and p2 - the points defining the edge of orientation K -
        // as the vertices at position orientation and orientation-1
        // that is, if we consider K = orientation * 2 (conversion from pi/4 multiple to pi/8 multiple)
        // then (K-1)/2 is the key we are looking for
        // with K+1 that just gets the original K (2K+1-1)/2=K and K-1 that gets the angle before (2K-1-1)/2=K-1

        $octVs = $this->getVertices();

        $p1 = $octVs[($this->direction+8)%8];
        $p2 = $octVs[($this->direction-1+8)%8];

        // find midpoint between them from which the norm originates
        $m = VektoracePoint::midpoint($p1, $p2);

        // calculate norm vector
        $n = VektoracePoint::displacementVector($m, $this->center);
        $n->invert();
        $n->normalize();

        return array( 'norm' => clone $n, 'origin' => $m); // origin is midpoint of front edge
    }
    
    // returns true if $this and $oct collide (uses SAT algo)
    public function collidesWith(VektoraceOctagon $oct) {

        // compute distance between octagons centers
        $distance = VektoracePoint::distance($this->center,$oct->center);

        // if it's a simple octagon and the distance is less then the size of the octagon itself, collision is assured
        if (!$this->isCurve && $distance < self::$size) return true;

        // run sat algo only if distance is less then the octagons radius, thus surrounding circles intersects. 
        if ($distance < 2*self::getOctProperties()['radius']) {

            $oct1 = $this->getVertices();
            $oct2 = $oct->getVertices();

            // if a separating axis exists on the standard plane, octagons arn't colliding
            if (self::findSeparatingAxis($oct1, $oct2)) return false;
            
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
            return !self::findSeparatingAxis($oct1, $oct2);
            
        } else return false;
    }

    // method takes two arrays of points as sets of vertices of a polygon
    // and returns true if a separating axis exists between them in their standard refernce plane
    // (to check other planes, rotate points and repeat)
    private static function findSeparatingAxis($poli1, $poli2) {
        
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
        if (!(($maxX2 < $maxX1 && $maxX2 > $minX1) || ($minX2 < $maxX1 && $minX2 > $minX1))) return true;

        // else check y coordinates
        $maxY1 = max($ysP1);
        $minY1 = min($ysP1);
        $maxY2 = max($ysP2);
        $minY2 = min($ysP2);

        // (as before, but for the y)
        // finally, if a intervals don't overlap for the y-axis, a separating axis exists (return true).
        // otherways no separating axis has been found (return false)
        return !(($maxY2 < $maxY1 && $maxY2 > $minY1) || ($minY2 < $maxY1 && $minY2 > $minY1));
    }

    public function collidesWithVector(VektoraceVector $vector) {

        if ($vector->getBottomOct()->collidesWith($this) || $vector->getTopOct()->collidesWith($this)) return true;
        
        $vectorInnerRect = $vector->innerRectVertices();
        $thisOct = $this->getVertices();

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

    // returns true if $this octagon is behing $oct, according to the line defined by the front-facing edge of $oct (towards its $direction)
    // the idea is to find the norm of this front-facing edge and see if the dot product with each vertex of $this octagon results in negative (thus together they form an angle greater than 90deg, which means the vertex is behind that edge)
    public function isBehind(VektoraceOctagon $oct) {

        ['norm'=>$n, 'origin'=>$m] = $oct->getDirectionNorm();

        // for each vertex of $this, find vector from m to the vertex and calculate dotproduct between them
        foreach ($this->getVertices() as $key => $vertex) {
            $v = VektoracePoint::displacementVector($m, $vertex);
            $v->normalize();

            if (VektoracePoint::dot($n, $v) >= -0.1) return false;
        }

        return true;
    }
}