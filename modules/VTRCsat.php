<?php

class VTRCsat { 
    
    // method takes array of vertices of two convex polygons (in the form [x,y])
    // and returns true if a separating axis exists between them in the original plane
    public static function findSeparatingAxis($poli1, $poli2) {
        // WELL???
        
        // extract all x and y coordinates to find extremes
        $xsP1 = [];
        $ysP1 = [];
        $xsP2 = [];
        $ysP2 = [];

        foreach ($poli1 as $key => $vertex) {
            $xsP1[] = $vertex[0];
            $ysP1[] = $vertex[1];
        }

        foreach ($poli2 as $key => $vertex) {
            $xsP2[] = $vertex[0];
            $ysP2[] = $vertex[1];
        }

        $maxX1 = max($xsP1);
        $minX1 = min($xsP1);
        $maxX2 = max($xsP2);
        $minX2 = min($xsP2);

        // if intervals defined by the respective extremes (for the x coordinates) don't overlap, a separating axis exists
        if (!(($maxX2 < $maxX1 && $maxX2 > $minX1) || ($minX2 < $maxX1 && $minX2 > $minX1))) {
            return true;
        } else {
            // else check y coordinates
            $maxY1 = max($ysP1);
            $minY1 = min($ysP1);
            $maxY2 = max($ysP2);
            $minY2 = min($ysP2);

            // (as before, but for the y)
            if (!(($maxY2 < $maxY1 && $maxY2 > $minY1) || ($minY2 < $maxY1 && $minY2 > $minY1))) {
                return true;
            } else {
                // if they both overlap, no separating axis exists
                return false;
            }
        }
    }

    /* // method takes array of vertices of two covex polygons (same as before)
    // and returns true if they collide. collision is detected by searching for separating axis
    // on standard and 45deg-rotated plane. if non is found, polygons are colliding.
    // (note that theese are the only two orientation needed for search, as in vektorace all elements fall in those orientations)
    public static function poligonsCollide($poli1, $poli2) {

        if(self::findSeparatingAxis($poli1, $poli2)) {
            return false;
        } else {
            // $omg = Math.PI / 4
            // $xr = x*Math.cos(omg) - y*Math.sin(omg);
            // $yr = x*Math.sin(omg) + y*Math.cos(omg);
            // $ar = a*Math.cos(omg) - b*Math.sin(omg);
            // $br = a*Math.sin(omg) + b*Math.cos(omg);
        }
    } */
}