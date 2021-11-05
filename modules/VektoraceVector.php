<?php

require_once('VektoracePoint.php');
require_once('VektoraceOctagon.php');

// classe used to handle all octagons operation and measurments
class VektoraceVector {

    private $center;
    private $direction;
    private $length;

    private $topOct;
    private $bottomOct;

    public function __construct(VektoracePoint $center, int $direction, int $length) {

        $this->center = clone $center;

        if ($direction<0 || $direction>7) throw new Exception("Invalid 'direction' argument. Value must be between 0 and 7", 1);       
        $this->direction = $direction;
        
        if ($direction<1 || $direction>5) throw new Exception("Invalid 'length' argument. Value must be between 1 and 5", 1);   
        $this->length = $length;

        if ($length == 1) {
            $this->topOct = $center;
            $this->bottomOct = $center;

        } else {

            $ro = ($length-1) * VektoraceOctagon::getOctProperties()['size'] / 2; // magnitude of translation, module of the translating vector
            $omg = $direction * M_PI_4; // direction of translation, angle of the translating vector

            $topPos = clone $center; // does pphp pass value or reference?? we gonna find out
            $bottomPos = clone $center;

            // apply translation to point
            $topPos->translate($ro*cos($omg), $ro*sin($omg));
            $bottomPos->translate(-$ro*cos($omg), -$ro*sin($omg));

            $this->topOct = new VektoraceOctagon($topPos, $direction);
            $this->bottomOct = new VektoraceOctagon($bottomPos, $direction);
        }
    }

    public function __clone() {

        $this->center = clone $this->center;
        $this->topOct = clone $this->topOct;
        $this->bottomOct = clone $this->bottomOct;
    }

    public static function constructFromAnchor(VektoraceOctagon $anchorOct, $length, $fromBottom = true) { // direction taken from anchor

        $centerPos = clone $anchorOct->getCenter();
        $direction = $anchorOct->getDirection();

        if ($length == 1) return new self($centerPos, $direction, $length);

        $ro = ($length-1) * VektoraceOctagon::getOctProperties()['size'] / 2;
        $omg = $direction * M_PI_4;

        if ($fromBottom) $centerPos->translate($ro*cos($omg), $ro*sin($omg));
        else $centerPos->translate(-$ro*cos($omg), -$ro*sin($omg));

        return new self($centerPos, $direction, $length);
    }

    public function getCenter() {
        return clone $this->center;
    }

    public function getDirection() {
        return $this->direction;
    }

    public function getLength() {
        return $this->length;
    }

    public function getTopOct() {
        return clone $this->topOct;
    }

    public function getBottomOct() {
        return clone $this->bottomOct;
    }

    public function innerRectVertices() {

        $bottomVs = $this->getBottomOct()->getVertices();
        $topVs = $this->getTopOct()->getVertices();

        $ret = array($topVs[0], $bottomVs[3], $bottomVs[4], $topVs[7]);

        $omg = (4 - $this->direction) * M_PI_4;
        foreach ($ret as &$p) {
            $p->changeRefPlane($this->center);
            $p->rotate($omg);
            $p->scale(1.07,1.35);
            $p->rotate(-$omg);
            $p->translate($this->center->x(),$this->center->y());
        }
        unset($p);

        return $ret;
    }

}