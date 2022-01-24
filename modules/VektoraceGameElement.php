<?php

require_once('VektoracePoint.php');

abstract class VektoraceGameElement {

    private static $size = 100;

    protected $center;
    protected $direction;

    public function __construct(VektoracePoint $center, int $direction) {

        $this->center = $center;

        if ($direction<0 || $direction>7) throw new Exception("Invalid direction argument. Value must be between 0 and 7", 1);       
        $this->direction = $direction;
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

        return array("size" => $size,
                     "side" => $side,
                     "corner" => $seg,
                     "radius" => $radius);
    }

    // returns VektoracePoint indicating geometric center of element
    public function getCenter() {
        return $this->center;
    }

    // returns int k indicating direction in which the element as the angle in randians k * PI/4
    public function getDirection() {
        return $this->direction;
    }

    // returns array of VektoracePoint indicating the vertices of the geometric element
    abstract public function getVertices();

    // returns true if collision between two *convex* polygon is detected on the plane
    // uses SeparatingAxisTheorem to determine collision, searching in only two plane of rotation (standard and 45deg)
    // takes also error margin to handle close collisions
    public static function detectSATcollision($poly1,$poly2, $err = 1) {
        
        if (self::findSeparatingAxis($poly1, $poly2, $err)) return false;
            
        $the = M_PI_4; // angle of rotation

        $poly1r = [];
        foreach ($poly1 as $v) {
            $poly1r[] = $v->rotate($the);
        }

        $poly2r = [];
        foreach ($poly2 as $v) {
            $poly2r[] = $v->rotate($the);
        }

        return !self::findSeparatingAxis($poly1r, $poly2r, $err);
    }

    // returns true if a separating axis is found between the two polygons on their reference plane
    public static function findSeparatingAxis($poly1, $poly2, $err = 0) {

        if (gettype($poly1) != 'array') throw new Exception('Polygon 1 must be an array of VektoracePoint objects');
        if (gettype($poly2) != 'array') throw new Exception('Polygon 2 must be an array of VektoracePoint objects');

        if (count($poly1) == 0) throw new Exception('Cannot detect collision for empty polygon 1');
        if (count($poly2) == 0) throw new Exception('Cannot detect collision for empty polygon 2');
        
        // separate x and y values for each polygon
        $P1X = $P1Y = [];
        $P2X = $P2Y = [];

        foreach ($poly1 as $vertex) {
            if (!is_a($vertex,'VektoracePoint')) throw new Exception('Polygon 1 must be an array of VektoracePoint objects');
            $P1X[] = $vertex->x();
            $P1Y[] = $vertex->y();
        }

        foreach ($poly2 as $vertex) {
            if (!is_a($vertex,'VektoracePoint')) throw new Exception('Polygon 2 must be an array of VektoracePoint objects');
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