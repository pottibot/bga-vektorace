<?php

require_once('VektoracePoint.php');
require_once('VektoraceOctagon.php');

// classe used to handle all octagons operation and measurments
class VektoraceVector {

    private VektoracePoint $center;
    private int $direction;
    private int $length;

    private VektoraceOctagon $topOct;
    private VektoraceOctagon $bottomOct;

    public function __construct($center, $direction, $length) {

        $this->center = $center;

        if ($direction<0 || $direction>7) throw new Exception("Invalid 'direction' argument. Value must be between 0 and 7", 1);       
        $this->direction = $direction;
        
        if ($direction<1 || $direction>5) throw new Exception("Invalid 'length' argument. Value must be between 1 and 5", 1);   
        $this->length = $length;

        $ro = ($length-1) * VektoraceOctagon::getOctProprieties()['size'] / 2; // magnitude of translation, module of the translating vector
        $omg = $direction * M_PI_4; // direction of translation, angle of the translating vector

        $topPos = $center; // does pphp pass value or reference?? we gonna find out
        $bottomPos = $center;

        // apply translation to point
        $topPos->translate($ro*cos($omg), $ro*sin($omg));
        $bottomPos->translate(-$ro*cos($omg), -$ro*sin($omg));

        $this->topOct = new VektoraceOctagon($topPos, $direction);
        $this->bottomOct = new VektoraceOctagon($bottomPos, $direction);
    }

    public static function constructFromAnchor(VektoraceOctagon $anchorOct, $length, $fromBottom = true) { // direction taken from anchor

        $centerPos = $anchorOct->getCenter();
        $direction = $bottomOct->getDirection();

        $ro = ($length-1) * VektoraceOctagon::getOctProprieties()['size'] / 2;
        $omg = $direction * M_PI_4;

        if ($fromBottom) $centerPos->translate($ro*cos($omg), $ro*sin($omg));
        else $centerPos->translate(-$ro*cos($omg), -$ro*sin($omg));

        return new self($centerPos, $direction, $length);
    }

    public function getCenter() {
        return $this->center;
    }

    public function getDirection() {
        return $this->direction;
    }

    public function getLength() {
        return $this->length;
    }

    public function getTopOct() {
        return $this->topOct;
    }

    public function getBottomOct() {
        return $this->bottomOct;
    }

    public function collidesWith(VektoraceOctagon $oct, $isCurve = false) {
    
    }

    public function collidesWithPitwall() {
    
    }

}