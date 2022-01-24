<?php

require_once('VektoraceGameElement.php');
require_once('VektoraceOctagon.php');

class VektoraceVector extends VektoraceGameElement{

    protected $length;

    protected $topOct;
    protected $bottomOct;

    // construct vector of certain length, from anchor point 'center', 'top' or 'bottom'
    public function __construct(VektoracePoint $anchorPoint, int $direction, int $length, $anchorPosition='center') {
        
        if ($length<1 || $length>5) throw new Exception("Invalid 'length' argument. Value must be between 1 and 5", 1);   
        $this->length = $length;
        $this->direction = $direction;

        if ($length == 1) {
            $this->center = $anchorPoint;
            $this->topOct = new VektoraceOctagon($this->center,$direction);
            $this->bottomOct = $this->topOct;

        } else {

            $ro = ($length-1) * self::getOctagonMeasures()['size']; // distance between top and bottom anchor points
            $the = $direction * M_PI_4;

            $topAnchorPoint;
            $bottomAnchorPoint;

            // based on anchor position alter point cordinates
            // could be done without the switch
            switch ($anchorPosition) {
                case 'center':
                    $this->center = $anchorPoint;
                    $topAnchorPoint = $anchorPoint->translatePolar($ro/2, $the);
                    $bottomAnchorPoint = $anchorPoint->translatePolar($ro/2, $the-M_PI);
                    
                    break;

                case 'top':
                    $this->center = $anchorPoint->translatePolar($ro/2, $the-M_PI);
                    $topAnchorPoint = $anchorPoint;
                    $bottomAnchorPoint = $anchorPoint->translatePolar($ro, $the-M_PI);
                    break;
                
                case 'bottom':
                    $this->center = $anchorPoint->translatePolar($ro/2, $the);
                    $topAnchorPoint = $anchorPoint->translatePolar($ro, $the);
                    $bottomAnchorPoint = $anchorPoint;

                    break;
                
                default: throw new Exception("Invalid anchor position. Should be 'center', 'top', or 'bottom'");
                    break;
            }
            
            $this->topOct = new VektoraceOctagon($topAnchorPoint, $direction);
            $this->bottomOct = new VektoraceOctagon($bottomAnchorPoint, $direction);
        }   
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

    public function getVertices() {

        $topOctVs = $this->topOct->getVertices();
        $botOctVs = $this->bottomOct->getVertices();

        if ($this->length == 1) return [$topOctVs];
        if ($this->length == 2) return [$topOctVs, $botOctVs];

        // else, calc vertices of inner rectangle
        $innerRectVs = array($topOctVs[0], $botOctVs[3], $botOctVs[4], $topOctVs[7]);

        // rescale rectangle to match actual shape
        $the = (4 - $this->direction) * M_PI_4;
        foreach ($innerRectVs as &$p) {
            $p = $p->translate(-$this->center->x(),-$this->center->y());
            $p = $p->rotate($the);
            $p = $p->scale(1.07,1.35);
            $p = $p->rotate(-$the);
            $p = $p->translate($this->center->x(),$this->center->y());
        } unset($p);

        return [$topOctVs,$innerRectVs,$botOctVs];
    }

    public function collidesWith(VektoraceGameElement $el, $err = 1) {

        $elPoly = $el->getVertices();

        foreach ($this->getVertices() as $vecComp) {

            // returned array contains more arrays, assuming those are separate convex polygon component forming a complex shape (see vectors)
            if (gettype($elPoly[0]) == 'array') {

                foreach ($elPoly as $i => $polyComp) {
                    if (self::detectSATcollision($vecComp, $polyComp, $err)) return true;
                }
    
            } else if (self::detectSATcollision($vecComp, $elPoly, $err)) return true;
        }

        return false;
    }

}