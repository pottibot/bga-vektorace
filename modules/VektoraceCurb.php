<?php

require_once('VektoraceCurve.php');

Class VektoraceCurb extends VektoraceCurve {

    public function __construct(int $number) {

        $dir;
        $center;

        switch ($number) {
            case 1:
                $dir = 5;
                $center = VektoracePoint2::midpoint(
                        new VektoracePoint2(-323,377),
                        new VektoracePoint2(-568,592)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side']/2, $dir*M_PI_4);
                break;
            
            case 2:
                $dir = 3;
                $center = VektoracePoint2::midpoint(
                        new VektoracePoint2(-568,959),
                        new VektoracePoint2(-323,1174)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side']/2, $dir*M_PI_4);
                break;

            case 3:
                $dir = 1;
                $center = VektoracePoint2::midpoint(
                        new VektoracePoint2(1467,1174),
                        new VektoracePoint2(1711,959)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side']/2, $dir*M_PI_4);
                break;

            case 4:
                $dir = 7;
                $center = VektoracePoint2::midpoint(
                        new VektoracePoint2(1711,592),
                        new VektoracePoint2(1467,377)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side']/2, $dir*M_PI_4);
                break;

            case 5:
                $dir = 2;
                $center = VektoracePoint2::midpoint(
                        new VektoracePoint2(430,868),
                        new VektoracePoint2(725,868)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side']/2, $dir*M_PI_4);
                break;

            default:
                throw new Exception("Invalid Curb number");
                break;
        }

        parent::__construct($center,$dir);
    }

    // set proper center?

    public function getVertices() {

        // get exact FIXED coordinates from map and generate vertices accordingly
        switch ($this->direction) {
            // curb 1, bottom left
            case 5:
                return [
                    new VektoracePoint2(-323,377),
                    new VektoracePoint2(-475,377),
                    new VektoracePoint2(-568,470),
                    new VektoracePoint2(-568,592),
                ];
                break;

            // curb 2, top left
            case 3:
                return [
                    new VektoracePoint2(-568,959),
                    new VektoracePoint2(-568,1081),
                    new VektoracePoint2(-475,1174),
                    new VektoracePoint2(-323,1174),
                ];
                break;

            // curb 3, top right
            case 1:
                return [
                    new VektoracePoint2(1467,1174),
                    new VektoracePoint2(1619,1174),
                    new VektoracePoint2(1711,1081),
                    new VektoracePoint2(1711,959),
                ];
                break;
            
            // curb 4, bottom right
            case 7:
                return [
                    new VektoracePoint2(1711,592),
                    new VektoracePoint2(1711,470),
                    new VektoracePoint2(1619,377),
                    new VektoracePoint2(1467,377),
                ];
                break;

            // curb 5, center
            case 2:
                return [
                    new VektoracePoint2(430,868),
                    new VektoracePoint2(454,901),
                    new VektoracePoint2(576,934),
                    new VektoracePoint2(701,901),
                    new VektoracePoint2(725,868),
                ];
                break;
        }

    }
}