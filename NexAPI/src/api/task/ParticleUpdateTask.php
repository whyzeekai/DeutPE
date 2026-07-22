<?php

declare(strict_types=1);

namespace API\task;

use API\Loader;

use pocketmine\level\particle\Particle;

use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\level\particle\HappyVillagerParticle;
use pocketmine\level\Position;

use pocketmine\scheduler\Task;

class ParticleUpdateTask extends Task {
    private $center;
    private $radius;

    public function __construct(Position $center, int $radius) {
        $this->center = $center;
        $this->radius = $radius;
    }

    public function onRun($currentTick) {
        $level = $this->center->getLevel();

        $colors = [
            [255, 0, 0],
            [0, 255, 0],    
            [0, 0, 255],    
            [255, 255, 0],  
            [255, 0, 255],  
            [0, 255, 255]   
        ];

        for ($i = 0; $i < 360; $i += 10) {
            $x = $this->center->x + cos(deg2rad($i)) * $this->radius;
            $z = $this->center->z + sin(deg2rad($i)) * $this->radius;
            $y = $this->center->y;

            $randomColor = $colors[array_rand($colors)];
            $r = $randomColor[0];
            $g = $randomColor[1];
            $b = $randomColor[2];

            $particle = new DustParticle(new Position($x, $y, $z, $level), $r, $g, $b);
            $level->addParticle($particle);
        }
    }
}
