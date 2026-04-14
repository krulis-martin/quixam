@question dynamic

@title
@en
Warp Speed Calculations
@cs
Výpočty warp rychlosti
@/title

@text
@en
Calculate the time it would take for a starship to travel to a star system **#DISTANCE# light years** away at **warp factor #WARP#**.

Write down the result in hours (rounded up to the nearest whole hour).
@cs
Vypočítejte čas, který by hvězdné lodi trvalo cestovat do systému vzdáleného **#DISTANCE# světelných let warp rychlostí #WARP#**.

Napište výsledek v hodinách (zaokrouhleno nahoru na nejbližší celé hodiny).
@/text

@code
    // Helper function to calculate travel time in hours based on distance in light years and warp factor.
    // This needs to be an anonymous function, we cannot declare named functions in the question text.
    $travelTime = function(float $distance, float $warp): int {
        $hoursInYear = 8766; // average number of hours in a year (365.25 days)
        $cSpeed = pow((float)$warp, 10.0 / 3.0); // speed in multiples of the speed of light
        return (int)ceil(($distance / $cSpeed) * $hoursInYear);
    };

    $warp = $this->random(20, 95); // warp speeds from 2.0 to 9.5 (in increments of 0.1)
    $distance = $this->random(15, 100); // distance from 1.5 to 10.0 light years
    $time = $travelTime($distance / 10.0, $warp / 10.0);

    // initialize question as a numeric question
    $question = $this->init('numeric');
    $question->setLimits(1, 1); // one-number answer

    // inject the calculated time as the correct answer
    $question->setCorrectAnswer([ $time ]);

    // replace placeholders in the question text with the actual distance and warp values
    // this is pure string replacement, but we selected the placeholders to use #NAME# format,
    // which is unlikely to appear in the text otherwise, so we can be sure we are replacing the correct parts
    $this->replaceText('#DISTANCE#', $distance / 10);
    $this->replaceText('#WARP#', $warp / 10);
@/code
