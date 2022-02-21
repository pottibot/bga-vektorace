<?php

require_once('VektoraceCurve.php');

Class VektoraceCurb extends VektoraceCurve {

    public function __construct(int $number) {

        $dir;
        $center;

        switch ($number) {
            
            case 1:
                $dir = 5;
                $center = VektoracePoint::midpoint(
                        new VektoracePoint(-441,379),
                        new VektoracePoint(-567,510)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side'], $dir*M_PI_4 + M_PI);
                break;
            
            case 2:
                $dir = 3;
                $center = VektoracePoint::midpoint(
                        new VektoracePoint(-567,1047),
                        new VektoracePoint(-436,1174)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side'], $dir*M_PI_4 + M_PI);
                break;

            case 3:
                $dir = 1;
                $center = VektoracePoint::midpoint(
                        new VektoracePoint(1579,1174),
                        new VektoracePoint(1711,1047)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side'], $dir*M_PI_4 + M_PI);
                break;

            case 4:
                $dir = 7;
                $center = VektoracePoint::midpoint(
                        new VektoracePoint(1711,510),
                        new VektoracePoint(1584,379)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side'], $dir*M_PI_4 + M_PI);
                break;

            case 5:
                $dir = 2;
                $center = VektoracePoint::midpoint(
                        new VektoracePoint(366,1148),
                        new VektoracePoint(809,1148)
                );
                $center = $center->translatePolar(self::getOctagonMeasures()['side']*2, $dir*M_PI_4 + M_PI);
                break;

            default:
                throw new Exception("Invalid Curb number");
                break;
        }

        parent::__construct($center,$dir);
    }

    public function getVertices() {

        // get exact FIXED coordinates from map and generate vertices accordingly
        switch ($this->direction) {
            // curb 1, bottom left
            case 5:
                return [
                    new VektoracePoint(-441,379),
                    new VektoracePoint(-475,379),
                    new VektoracePoint(-567,471),
                    new VektoracePoint(-567,510),
                ];
                break;

            // curb 2, top left
            case 3:
                return [
                    new VektoracePoint(-567,1047),
                    new VektoracePoint(-567,1081),
                    new VektoracePoint(-475,1174),
                    new VektoracePoint(-436,1174),
                ];
                break;

            // curb 3, top right
            case 1:
                return [
                    new VektoracePoint(1579,1174),
                    new VektoracePoint(1619,1174),
                    new VektoracePoint(1711,1081),
                    new VektoracePoint(1711,1047),
                ];
                break;
            
            // curb 4, bottom right
            case 7:
                return [
                    new VektoracePoint(1711,510),
                    new VektoracePoint(1711,471),
                    new VektoracePoint(1619,379),
                    new VektoracePoint(1584,379),
                ];
                break;

            // curb 5, center
            case 2:
                return [
                    new VektoracePoint(366,1148),
                    new VektoracePoint(400,1174),
                    new VektoracePoint(773,1174),
                    new VektoracePoint(809,1148),
                ];
                break;
        }

    }

    public static function pointBeyondLimit(VektoracePoint $point, $limit = 'oval') {

        switch ($limit) {
            case 'oval':
                
                $oval = [
                    new VektoracePoint(-462,402),
                    new VektoracePoint(-544,484),
                    new VektoracePoint(-544,1067),
                    new VektoracePoint(-462,1150),
                    new VektoracePoint(1604,1150),
                    new VektoracePoint(1687,1067),
                    new VektoracePoint(1687,484),
                    new VektoracePoint(1604,402),
                ];

                $p = [$point, $point]; // sat algo only accept array of points

                return self::detectSATcollision($p, $oval);
                break;

            case 'triangle':

                $lsPoint = new VektoracePoint(-419.07, 608.76);
                $lsAng = 124.5 * M_PI / 180;
                $lsNorm = new VektoracePoint(); $lsNorm = $lsNorm->translatePolar(1,$lsAng);
                
                $behindLs = VektoracePoint::dot($lsNorm, VektoracePoint::displacementVector($lsPoint, $point)) < 0;

                $rsPoint = new VektoracePoint(812.19, 1145.77);
                $rsAng = 54.7 * M_PI / 180;
                $rsNorm = new VektoracePoint(); $rsNorm = $rsNorm->translatePolar(1,$rsAng);
                
                $behindRs = VektoracePoint::dot($rsNorm, VektoracePoint::displacementVector($rsPoint, $point)) < 0;

                return  $behindLs && $behindRs;
                break;

            case 'left':

                $lPoint = new VektoracePoint(-826.7, 826.7);
                $lAng = 3 * M_PI_4;
                $lNorm = VektoracePoint::createPolarVector(1,$lAng);

                return VektoracePoint::dot($lNorm, VektoracePoint::displacementVector($lPoint, $point)) > 0;
                break;

            case 'right':

                $rPoint = new VektoracePoint(1400, 1400);
                $rAng = M_PI_4;
                $rNorm = VektoracePoint::createPolarVector(1,$rAng);

                return VektoracePoint::dot($rNorm, VektoracePoint::displacementVector($rPoint, $point)) > 0;
                break;
        }
    }
}