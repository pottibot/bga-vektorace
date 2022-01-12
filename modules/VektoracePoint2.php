<?php

// Class to describe a point on a bidimensional plane. Also contains all useful operations that involves points and vectors
class VektoracePoint2 {
    
    private $x;
    private $y;

    public function __construct($x=0,$y=0) {
        $this->x = floatval($x);
        $this->y = floatval($y);
    }

    public function __toString() {
        return '('.$this->x.', '.$this->y.')';
    }

    // get x cooordinate
    public function x() {
        return $this->x;
    }

    // get y cooordinate
    public function y() {
        return $this->y;
    }

    // returns coordinates in array form and rounded values
    public function coordinates() {
        return array('x' => round($this->x), 'y' => round($this->y));
    }

    // invert point coordinates
    public function invert() {
        return new self(-$this->x,-$this->y);
    }

    // applies simple counter-clockwise rotation to point coordinates
    public function rotate($the) {

        $c = cos($the);
        $s = sin($the);

        return new self($this->x*$c - $this->y*$s, $this->x*$s + $this->y*$c);
    }

    // applies simple translation to point coordinates
    public function translate($tx, $ty) {
        return new self($this->x + $tx, $this->y + $ty);
    }

    // applies translation using polar coordinates
    public function translatePolar($ro, $the) {
        return $this->translate($ro*cos($the), $ro*sin($the));
    }

    // applies simple translation to point coordinates
    public function scale($sx, $sy) {

        return new self($this->x * $sx, $this->y * $sy);
    }

    // change reference origin and applies transformation (scale and rot), then return transformed point to previous reference origin
    public function transformFromOrigin(VektoracePoint2 $origin, $sx, $sy, $the = 0) {

        $centered = $this->translate(-$this->x, -$this->y);
        $scaled = $centerd->scale($sx,$sy);
        $rotated = $scaled->rotate($the);
        
        return $rotated->translate($this->x, $this->y);
    }

    // calculates euclidean distance between point1 and point2
    public static function distance(VektoracePoint2 $p1, VektoracePoint2 $p2) {

        return sqrt(pow($p2->x - $p1->x, 2) + pow($p2->y - $p1->y, 2));
    }

    // find median midpoint between point1 and point2
    public static function midpoint(VektoracePoint2 $p1, VektoracePoint2 $p2) {

        $mx = ($p1->x + $p2->x)/2;
        $my = ($p1->y + $p2->y)/2;

        return new self($mx, $my);
    }

    // calculates displacement vector between origin and end point
    public static function displacementVector(VektoracePoint2 $origin, VektoracePoint2 $point) {

        $vx = $point->x - $origin->x;
        $vy = $point->y - $origin->y;

        return new VektoracePoint2($vx, $vy);
    }

    // calculates norm of point vector from origin
    public function normalize() {

        $mag = self::distance(new VektoracePoint2(0,0), $this);

        return new self($this->x / $mag, $this->y / $mag);
    }

    // calculates dot product between two points
    public static function dot(VektoracePoint2 $v1, VektoracePoint2 $v2) {

        $d1 = $v1->x * $v2->x;
        $d2 = $v1->y * $v2->y;

        return $d1 + $d2;
    }
}