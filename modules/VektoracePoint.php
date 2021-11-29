<?php

// Class to describe a point on a bidimensional plane. Also contains all useful operations that involves points and vectors
class VektoracePoint {
    
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
        //return array(round($this->x),round($this->y));
    }

    // invert point coordinates
    public function invert() {

        $this->x = -$this->x;
        $this->y = -$this->y;
    }

    // applies simple counter-clockwise rotation to point coordinates
    public function rotate($omg) {

        $c = cos($omg);
        $s = sin($omg);

        $xr = $this->x*$c - $this->y*$s;
        $yr = $this->x*$s + $this->y*$c;

        $this->x = $xr;
        $this->y = $yr;
    }

    // applies simple translation to point coordinates
    public function translate($tx, $ty) {

        $this->x = $this->x + $tx;
        $this->y = $this->y + $ty;
    }

    public function translateVec($ro, $omg) {
        $this->translate($ro*cos($omg), $ro*sin($omg));
    }

    // applies simple translation to point coordinates
    public function scale($sx, $sy) {

        $this->x = $this->x * $sx;
        $this->y = $this->y * $sy;
    }

    // applies translation to $this point so that its coordinates refer to a plane that has center (0,0) in $origin. useful for centered rotations
    public function changeRefPlane(VektoracePoint $origin) {

        $this->translate(-$origin->x, -$origin->y);
    }

    // calculates euclidean distance between point1 and point2
    public static function distance(VektoracePoint $p1, VektoracePoint $p2) {

        return sqrt(pow($p2->x - $p1->x, 2) + pow($p2->y - $p1->y, 2));
    }

    // find median midpoint between point1 and point2
    public static function midpoint(VektoracePoint $p1, VektoracePoint $p2) {

        $mx = ($p1->x() + $p2->x())/2;
        $my = ($p1->y() + $p2->y())/2;

        return new VektoracePoint($mx, $my);
    }

    // calculates displacement vector between origin and end point
    public static function displacementVector(VektoracePoint $origin, VektoracePoint $point) {

        $vx = $point->x - $origin->x;
        $vy = $point->y - $origin->y;

        return new VektoracePoint($vx, $vy);
    }

    // calculates norm of vector
    public function normalize() {

        $mag = self::distance(new VektoracePoint(0,0), $this);

        $this->x = $this->x / $mag;
        $this->y = $this->y / $mag;
    }

    // calculates dot product between two points
    public static function dot(VektoracePoint $v1, VektoracePoint $v2) {

        $d1 = $v1->x * $v2->x;
        $d2 = $v1->y * $v2->y;

        return $d1 + $d2;
    }

}