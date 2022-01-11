<?php

require_once('VektoraceVector2.php');

// not a singleton
class VektoracePitwall extends VektoraceGameElement {

    private $vector;

    public function __construct(VektoracePoint $center, int $direction) {

        $this->vector = new VektoraceVector2($center, $direction, 4);
    }

    public function getVertices() {
        $pitwallPolys = $this->vector->getVertices();

        foreach ($pitwallPolys as &$poly) {
            foreach ($poly as &$v) {
                $v = $v->scaleAndRotateFromOrigin($this->center, 0.75, 0.75, 0);
            } unset($v);
        } unset($poly);

        return $pitwallPolys;
    }

}