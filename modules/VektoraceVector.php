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

    public function __construct(VektoracePoint $anchorPoint, int $direction, int $length, $anchorPosition='center') {

        if ($direction<0 || $direction>7) throw new Exception("Invalid 'direction' argument. Value must be between 0 and 7", 1);       
        $this->direction = $direction;
        
        if ($length<1 || $length>5) throw new Exception("Invalid 'length' argument. Value must be between 1 and 5", 1);   
        $this->length = $length;

        if ($length == 1) {
            $this->center = clone $anchorPoint;
            $this->topOct = new VektoraceOctagon($this->center,$direction);
            $this->bottomOct = $this->topOct;
        }

        $ro = ($length-1) * VektoraceOctagon::getOctProperties()['size']; // distance between top and bottom anchor points
        $omg = $direction * M_PI_4;

        $this->center = clone $anchorPoint;
        $topAnchorPoint = clone $anchorPoint;
        $bottomAnchorPoint = clone $anchorPoint;

        // based on anchor position alter point cordinates
        // could be done without the switch
        switch ($anchorPosition) {
            case 'center':
                // $this->center->translateVec(0, $omg);
                $topAnchorPoint->translateVec($ro/2, $omg);
                $bottomAnchorPoint->translateVec($ro/2, $omg-M_PI);
                
                break;

            case 'top':
                $this->center->translateVec($ro/2, $omg-M_PI);
                // $topAnchorPoint->translateVec(0, $omg);
                $bottomAnchorPoint->translateVec($ro, $omg-M_PI);

                break;
            
            case 'bottom':
                $this->center->translateVec($ro/2, $omg);
                $topAnchorPoint->translateVec($ro, $omg);
                // $bottomAnchorPoint->translateVec(0, $omg);

                break;
            
            default: throw new Exception("Invalid anchor position. Should be 'center', 'top', or 'bottom'");
                break;
        }
        
        $this->topOct = new VektoraceOctagon($topAnchorPoint, $direction);
        $this->bottomOct = new VektoraceOctagon($bottomAnchorPoint, $direction);
        
    }

    public function __clone() {

        $this->center = clone $this->center;
        $this->topOct = clone $this->topOct;
        $this->bottomOct = clone $this->bottomOct;
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
        return $this->topOct;
    }

    public function getBottomOct() {
        return $this->bottomOct;
    }

    // returns list of vertices of inner rectangle of vector element
    public function innerRectVertices() {

        $bottomVs = $this->getBottomOct()->getVertices();
        $topVs = $this->getTopOct()->getVertices();

        $ret = array($topVs[0], $bottomVs[3], $bottomVs[4], $topVs[7]);

        $omg = ($this->direction - 4) * M_PI_4;
        foreach ($ret as &$p) {
            $p->changeRefPlane($this->center);
            $p->rotate($omg);
            $p->scale(1.07,1.35);
            $p->rotate(-$omg);
            $p->translate($this->center->x(),$this->center->y());
        } unset($p);

        return $ret;
    }

}