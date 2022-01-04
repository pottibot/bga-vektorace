<?php

require_once('VektoracePoint.php');

abstract class VektoraceElement {

    private $size = 100;

    protected $center;
    protected $direction;

    public function __construct(VektoracePoint $center, int $direction) {

        $this->center = clone $center;

        if ($direction<0 || $direction>7) throw new Exception("Invalid direction argument. Value must be between 0 and 7", 1);       
        $this->direction = $direction;
    }

    public function __clone() {
        $this->center = clone $this->center;
    }

    // returns associative array with all the useful measures to work with octagon geometry
    //  > size: diameter of the octagon, side of the box inscribing it
    //  > side: length of all the equal sides of the octagon
    //  > corner: difference between size and side of the of the octagon, that is also the base of the rectangular triangle formed on each corner of the box inscribing the octagon
    //  > radius: radius of the circle that inscribing the octagon, distance between the center and the furthest vertices of the octagon
    public static function getOctagonMeasures() {
        $size = self::$size;
        $side = $size / (1 + 2/sqrt(2));
        $seg = ($size - $side) / 2;
        $radius = sqrt(pow($size/2,2) + pow($side/2,2));

        return array("size" => self::$size,
                     "side" => $side,
                     "corner" => self::$size - $side,
                     "radius" => $radius);
    }

    // returns VektoracePoint indicating geometric center of element
    public function getCenter() {
        return clone $this->center;
    }

    // returns int k indicating direction in which the element as the angle in randians k * PI/4
    public function getDirection() {
        return clone $this->direction;
    }

    // returns array of VektoracePoint indicating the vertices of the geometric element
    abstract public function getVertices();
    
    // returns true if this elemen collides with another one on the plane (see below for collision algo)
    abstract public function collidesWith(VektoraceElement $element);

    // returns true if collision between two convex poligon is detected on the plane
    // uses SeparatingAxisTheorem to determine collision, searching in only two plane of rotation (standard and 45deg)
    // takes also error margin to handle close collisions
    public static function detectSATcollision($poli1,$poli2, $err = 1) {
        
        if (self::findSeparatingAxis($poli1, $poli2, $err)) return false;
            
        $the = M_PI_4; // angle of rotation

        foreach ($poli1 as &$v) {
            $v = clone $v; // bit weird, needed to not modify original polygon vertices
            $v->rotate($the);
        }
        unset($v);

        foreach ($poli2 as &$v) {
            $v = clone $v;
            $v->rotate($the);
        }
        unset($v);

        return !self::findSeparatingAxis($poli1, $poli2, $err);
    }

    // returns true if a separating axis is found between the two poligons on their reference plane
    public static function findSeparatingAxis($poli1, $poli2, $err = 0) {
        
        // separate x and y values for each polygon
        $P1X = $P1Y = [];
        $P2X = $P2Y = [];

        foreach ($poli1 as $vertex) {
            $P1X[] = $vertex->x();
            $P1Y[] = $vertex->y();
        }

        foreach ($poli2 as $vertex) {
            $P2X[] = $vertex->x();
            $P2Y[] = $vertex->y();
        }
        
        // find extremes to determine the interval each poly covers on the x axis
        $P1a = min($P1X)+$err; // add rounding errors (makes intervals slightly smaller)
        $P1b = max($P1X)-$err;
        
        $P2a = min($P2X)+$err;
        $P2b = max($P2X)-$err;

        // if poly1 interval ends before beginning of poly2 interval OR poly1 interval begins after end of poly2 interval -> intervals don't overlap, a separating axis exists
        if ($P1b < $P2a || $P1a > $P2b) return true;

        // else check y-axis
        // extract y-axis intervals
        $P1a = min($P1Y)+$err; // add rounding errors
        $P1b = max($P1Y)-$err;
        
        $P2a = min($P2Y)+$err;
        $P2b = max($P2Y)-$err;

        // (as before, but for the y)
        return $P1b < $P2a || $P1a > $P2b; // if true intervals don't overlap -> separating axis exists | if false intervals overlap -> no separating axis has ben found on this plane
    }
}