<?php

require_once('VektoraceCurve.php');

Class VektoraceCurb extends VektoraceGameElement {

    public function getVertices() {

        switch ($this->direction) {
            case 5:
                break;

            case 3:
                break;

            case 1:
                break;
            
            case 7:
                break;

            case 2:
                break;
            
            default:
                throw new Exception('Curb with this orientation does not exist');
                break;
        }

    }
}