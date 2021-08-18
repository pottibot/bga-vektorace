<?php

require_once('VTRCsat.php');

// classe used to handle all octagons operation and measurments
class VTRCoctagon {
    // size of the box that inscribes the octagon, orizontal diameter
    // implicitly defines scale of all immaginary octagons
    // meaning operations that find new oct positions (eg. flyingStartPositions) use those distances to calculate new coordinates
    private static $size = 100;

    // octagon center coordinates
    private $x;
    private $y;

    public function __construct($x,$y) {
        $this->x = $x;
        $this->y = $y;
    }
    
    // returns all useful measures when dealing with octagons
    public static function getOctProprieties() {
        $sidlen = self::$size / (1 + 2/sqrt(2)); // length of all equal sides of the octagon
        $cseg = $sidlen / sqrt(2); // half the length of the segment resulting from size - side. or the cathetus of the rectangular triangle built on the angle of the box which inscribes the octagon.
        $radius = sqrt(pow(self::$size/2,2) + pow($sidlen/2,2)); // radius of the circle that inscribes the octagon. or the distance between the center and its furthest apexes

        return array("size"=>           self::$size,
                     "side"=>           $sidlen,
                     "corner_seg"=>     $cseg,
                     "radius"=>         $radius);
    }

    // given a direction (which can be a cardinal point or an integer as the index of a 8-stops clock) and an ammount (from 1 to 8)
    // it returns a indexed list (by cardinal points) containing all the extracted coordinates (as an array [x,y])
    public function getAdiacentOctagons($direction,$amount) {

        // if int key to parse the clock is the direction argument
        if (is_int($direction) && $direction<8 && $direction>=0) {
            $key = $direction;
        } else {
            // else is the index of the element matching the cardinal point
            $dirs = array('N','NE','E','SE','S','SO','O','NO');
            $key = array_search($direction,$dirs);
            // EXCEPTION TODO
            // if (!$key) throw new Exception("Wrong direction format", 1);
        }

        // to extract all coordinates of the given amount, clock is parsed clock-wise, by index which is the key minus half of the amount (its easier to visualize this on paper)
        // plus 8 to make modulus returns only positive values (which will be the clock indicies)
        $key -= ($amount-1)/2;
        $key += 8;

        // temp variables to use short names
        $s = self::$size;
        $u = self::getOctProprieties()["side"];
        $x = $this->x;
        $y = $this->y;

        $ret = array();

        // for amount times, extract one pair of coordinates, but it in the returned array and repeat
        for ($i=0; $i < $amount; $i++) {

            // depending on the index of the clock, operation to produce new coordinates is different
            switch ($key%8) {
                case 0: $ret['N'] = array($x, $y+$s);
                        break;
    
                case 1: $ret['NE'] = array(round($x+$u/2+$s/2), round($y+$u/2+$s/2));
                        break;
                    
                case 2: $ret['E'] = array($x+$s, $y);
                        break;
    
                case 3: $ret['SE'] = array(round($x+$u/2+$s/2), round($y-$u/2-$s/2));
                        break;
    
                case 4: $ret['S'] = array($x, $y-$s);
                        break;
    
                case 5: $ret['SO'] = array(round($x-$u/2-$s/2), round($y-$u/2-$s/2));
                        break;
    
                case 6: $ret['O'] = array($x-$s, $y);
                        break;
    
                case 7: $ret['NO'] = array($x-$u/2-$s/2, $y+$u/2+$s/2);
                        break;
            }

            $key++;
        }

        return $ret;
    }

    // given a direction (same as before) it generates all possible flying-start position
    // which means finding the adiecent 3 octagons in that direction and do the same for these returned octagons in the respective direction
    public function flyingStartPositions($direction) {
        $ret = array();

        $fs = $this->getAdiacentOctagons($direction,3);

        foreach ($fs as $dir => $pos) {
            $oct = new self($pos[0],$pos[1]);
            $ret[] = array_values($oct->getAdiacentOctagons($dir,3));
        }

        // merge all in one single array
        return array_merge(array_merge($ret[0],$ret[1]),$ret[2]);
    }

    // returns array of all vertices of $this octagon
    public function getVertices() {
        // get all useful proprieties to calculate the position of all vertices
        $octMeasures = self::getOctProprieties();
        $siz = $octMeasures['size'];
        $sid = $octMeasures['side'];
        $seg = $octMeasures['corner_seg'];

        // shift octagon coordinates from center to top-left corner of the box inscribing the octagon
        $x = $this->x - self::$size/2;
        $y = $this->y - self::$size/2;

        return array(
            [$x+$seg, $y], [$x+$seg+$sid, $y],              //   *  *         
            [$x, $y+$seg], [$x+$siz, $y+$seg],              // *      *            
            [$x, $y+$seg+$sid], [$x+$siz, $y+$seg+$sid],    // *      *             
            [$x+$seg, $y+$siz], [$x+$seg+$sid, $y+$siz]     //   *  *              
        );
    }

    // returns array of all vertices of a curve element (a kind of octgagon split by its diagonal), given $this octagon (split diagonal 45deg from center. takes up all vertices in the top-left corner, plus two from the diagonal)
    public function curveVertices($direction) {
        //
        //       *  2  * 
        //     5         1
        //   *             * 
        //   4      x      0  ->
        //   *             * 
        //     6         8
        //       *  7  *   
        //

        // works as method above
        $octMeasures = self::getOctProprieties();
        $siz = $octMeasures['size'];
        $sid = $octMeasures['side'];
        $seg = $octMeasures['corner_seg'];

        $x = $this->x - ($sid+$seg)/2;
        $y = $this->y - ($sid+$seg)/2;

        $ret = array(
                [$x+$seg, $y], [$x+$seg+$sid, $y],              //   *  *         
                [$x, $y+$seg], [$x+$sid+$seg, $y+$seg],         // *    *            
                [$x, $y+$seg+$sid], [$x+$sid, $y+$seg+$sid]     // *  *                        
               );
        
        $omg = ($direction - 3) * M_PI_4;
        foreach ($ret as $i => $p) {
            $p = array($p[0] - $this->x, $p[1] - $this->y);
            $pr = self::rotatePoint($p, $omg);

            $ret[$i] = array($pr[0] + $this->x, $pr[1] + $this->y);
        }

        return $ret;
    }

    // rotate point counter clock wise
    private static function rotatePoint($point, $omg) {
        $x = $point[0];
        $y = $point[1];

        $c = cos($omg);
        $s = sin($omg);

        $xr = $x*$c - $y*$s;
        $yr = $x*$s + $y*$c;

        return array($xr,$yr);
    }
    
    // returns true if $this and $oct collide (uses SAT algo)
    public function collidesWith($oct, $isCurve = false, $curveDir = null) {
        $x1 = $this->x;
        $y1 = $this->y;

        $x2 = $oct->x;
        $y2 = $oct->y;

        // compute distance between octagons centers
        $distance = sqrt(pow($x1-$x2,2) + pow($y1-$y2,2));

        if (!$isCurve && $distance < self::$size) {
            return true;
        }

        // run sat algo only if distance is less then the octagons radius, thus surrounding circles intersects
        if ($distance < 2*self::getOctProprieties()['radius']) {

            $oct1 = $this->getVertices();
            $oct2 = $isCurve? $oct->curveVertices($curveDir) : $oct->getVertices();

            // if a separating axis exists on the standard plane, octagons arn't colliding
            if (VTRCsat::findSeparatingAxis($oct1, $oct2)) {
                return false;
            } else {
                // else, rotate plane 45deg and check there

                $omg = M_PI_4;

                foreach ($oct1 as $i => $vertex) {
                    $oct1[$i] = self::rotatePoint($vertex, $omg);
                }

                foreach ($oct2 as $i => $vertex) {
                    $oct2[$i] = self::rotatePoint($vertex, $omg);
                }

                if (VTRCsat::findSeparatingAxis($oct1, $oct2)) {
                    return false;
                } else {
                    // if no separating axis is found, octagons are colliding
                    return true;
                }
            }
        } else return false;
    }

    // returns true if $this octacon collides with the pitwall table element, assumed to be always positioned at (0,0) with k=4 orientation and size 398x100px
    public function collidesWithPitwall() {
        // width x height
        $w = 398;
        $h = 100;
        $t = 55.17; // assumed thickness of the inner rectangle
        $e = 7.66; // error difference between the size of the regular octagon and the pitwall ones, which are cut by the thickness of the rectangle

        $octMeasures = self::getOctProprieties();
        $siz = $octMeasures['size'];

        // separate pitwall in three elements, two octagon at the extremes of the shape and one rectangle in the middle that joins them together
        //   *  *                *  *       
        // *      *   . . .    *      *            
        // *      *   . . .    *      *         
        //   *  *                *  *     

        // check collision with each individual element, if a collision is detected for at least one element, then $this octagon collides with the pitwall. 
        $x1 = -$w/2 + $siz/2;
        $oct1 = new self($x1, 0);
        if ($this->collidesWith($oct1)) return true;

        $x2 = +$w/2 - $siz/2;
        $oct2 = new self($x2, 0);
        if ($this->collidesWith($oct2)) return true;

        // for the rectangle, it's a simple check of coordinates interval
        // if any of the vertex fall into the intervals defined by the coordinates of the rectangle vertices, then the octagon collides with it
        foreach ($this->getVertices() as $key => $vertex) {
            if (abs($vertex[0]) < ($w-$siz)/2 && abs($vertex[1]) < $t/2) return true;
        }

        // doesn't work
        /* $rect = array(array($x1 + $siz/2 - $e, $t/2), array($x2 - $siz/2 + $e, $t/2), array($x2 - $siz/2 + $e, -$t/2), array($x1 + $siz/2 - $e, -$t/2));
        if (!VTRCsat::findSeparatingAxis($this->getVertices(), $rect)) return true; */

        // if no collision
        return false;

    }

    public function isBehind($oct, $direction) {

        // OPTIMIZABLE BY JUST STORING THE VERTICIES IN A REASONABLE ORDER
        // THEN JUST EXTRACT THOSE FROM THE OCTAGON INSTEAD OF CALCULTATING IT ALWAYS FORM SCRATCH

        // direction indicates the side which counts as forward (es: 0 indicates that the octagon is pointing east)
        //
        //       *  2  * 
        //     5         1
        //   *             * 
        //   4      x      0  ->
        //   *             * 
        //     6         8
        //       *  7  *   
        //
        // calculates norm vector of corresponding forward edge using as: direction * 2 * pi/8

        $octC = array($oct->x, $oct->y);
        $radius = self::getOctProprieties()['radius'];

        $omg = $direction*2 * M_PI/8;
        $v = array(cos($omg)*$radius + $octC[0], sin($omg)*$radius + $octC[1]);

        $omg = ($direction*2 + 1) * M_PI/8;
        $p1 = array(cos($omg)*$radius + $octC[0], sin($omg)*$radius + $octC[1]);

        $omg = ($direction*2 - 1) * M_PI/8;
        $p2 = array(cos($omg)*$radius + $octC[0], sin($omg)*$radius + $octC[1]);

        $m = array(($p1[0]+$p2[0])/2, ($p1[1]+$p2[1])/2);

        $n = array($v[0]-$m[0], $v[1]-$m[1]);

        foreach ($this->getVertices() as $key => $vertex) {
            $w = array($vertex[0]-$m[0], $vertex[1]-$m[1]);

            $dotp = $n[0]*$w[0] + $n[1]*$w[1];

            if ($dotp >= 0) return false;
        }

        return true;
    }
}