<?php

namespace Test\Shooting;

use cs\Core\GameProperty;
use cs\Core\HitBox;
use cs\Core\Player;
use cs\Core\Wall;
use cs\Enum\BuyMenuItem;
use cs\Enum\Color;
use cs\Enum\HitBoxType;
use cs\Weapon\PistolUsp;
use cs\Weapon\RifleAk;
use cs\Weapon\RifleM4A4;
use Test\BaseTestCase;

class PlayerKillTest extends BaseTestCase
{

    public function testOnePlayerCanKillOther(): void
    {
        $startMoney = 6000;
        $player2Commands = [
            fn(Player $p) => $p->buyItem(BuyMenuItem::RIFLE_M4A4),
            fn(Player $p) => $p->getSight()->lookHorizontal(180),
            $this->waitNTicks(RifleAk::equipReadyTimeMs) - 2,
            $this->endGame(),
        ];
        $player2 = new Player(2, Color::GREEN, false);

        $game = $this->createGame([GameProperty::START_MONEY => $startMoney]);
        $game->addPlayer($player2);
        $this->playPlayer($game, $player2Commands, $player2->getId());
        $this->assertTrue($game->getScore()->isTie());

        $result = $player2->attack();
        $gun = $player2->getEquippedItem();
        $this->assertInstanceOf(RifleM4A4::class, $gun);
        $this->assertNotNull($result);
        $hits = $result->getHits();
        $this->assertSame($gun->getKillAward(), $result->getMoneyAward());
        $this->assertCount(2, $hits);
        $headHit = $hits[0];
        $this->assertInstanceOf(HitBox::class, $headHit);
        $this->assertInstanceOf(Wall::class, $hits[1]);

        $playerOne = $headHit->getPlayer();
        $this->assertInstanceOf(Player::class, $playerOne);
        $this->assertFalse($playerOne->isAlive());
        $this->assertNull($player2->attack());
        $this->assertTrue($headHit->getType() === HitBoxType::HEAD);
        $this->assertSame($startMoney - $gun->getPrice() + $gun->getKillAward(), $player2->getMoney());
    }

    public function testUspKillPlayerInThreeBulletsInChestWithNoKevlar(): void
    {
        $player2Commands = [
            fn(Player $p) => $p->getSight()->lookAt(180, 0),
            fn(Player $p) => $p->crouch(),
            $this->waitNTicks(max(Player::tickCountCrouch, PistolUsp::equipReadyTimeMs)),
            $this->endGame(),
        ];
        $player2 = new Player(2, Color::GREEN, false);

        $game = $this->createGame();
        $game->addPlayer($player2);
        $player1 = $game->getPlayer(1);
        $this->playPlayer($game, $player2Commands, $player2->getId());

        $this->assertSame(100, $player1->getHealth());
        $result = $player2->attack();
        $this->assertNotNull($result);
        $gun = $player2->getEquippedItem();
        $this->assertInstanceOf(PistolUsp::class, $gun);
        $this->assertSame(0, $result->getMoneyAward());

        $hits = $result->getHits();
        $this->assertCount(2, $hits);
        $bodyShot = $hits[0];
        $this->assertInstanceOf(HitBox::class, $bodyShot);
        $this->assertSame(HitBoxType::CHEST, $bodyShot->getType());
        $this->assertInstanceOf(Wall::class, $hits[1]);

        $this->assertLessThan(100, $player1->getHealth());
        $this->assertTrue($player1->isAlive());
        $this->assertTrue($player2->isAlive());

        $tickId = $game->getTickId();
        $game->tick(++$tickId);
        $this->assertNotNull($player2->attack());
        $this->assertTrue($player1->isAlive());
        $this->assertTrue($player2->isAlive());

        $game->tick(++$tickId);
        $this->assertNotNull($player2->attack());
        $this->assertFalse($player1->isAlive());
        $this->assertTrue($player2->isAlive());

        $game->tick(++$tickId);
        $this->assertFalse($game->getScore()->attackersIsWinning());
        $this->assertSame(0, $game->getScore()->getScoreAttackers());
        $this->assertSame(1, $game->getScore()->getScoreDefenders());
        $this->assertFalse($game->getScore()->isTie());
    }

    public function testPlayerCanDodgeBulletByCrouch(): void
    {
        $player2 = new Player(2, Color::GREEN, false);
        $player2->getSight()->lookAt(180, 0);

        $game = $this->createGame();
        $game->addPlayer($player2);
        $game->getPlayer(1)->crouch();

        for ($i = 1; $i <= Player::tickCountCrouch; $i++) {
            $game->tick($i);
        }
        $this->assertSame(Player::headHeightCrouch, $game->getPlayer(1)->getHeadHeight());
        $this->assertSame(Player::headHeightStand, $player2->getHeadHeight());
        $result = $player2->attack();
        $this->assertNotNull($result);

        $hits = $result->getHits();
        $this->assertCount(1, $hits);
        $this->assertInstanceOf(Wall::class, $hits[0]);
    }

    public function testPlayerCanDodgeBulletByCrouchWithAngleDown(): void
    {
        $player2 = new Player(2, Color::GREEN, false);
        $player2->getSight()->lookAt(180, -20);

        $game = $this->createGame();
        $game->addPlayer($player2);
        $game->getPlayer(1)->crouch();

        for ($i = 1; $i <= Player::tickCountCrouch; $i++) {
            $game->tick($i);
        }
        $this->assertSame(Player::headHeightCrouch, $game->getPlayer(1)->getHeadHeight());
        $this->assertSame(Player::headHeightStand, $player2->getHeadHeight());
        $result = $player2->attack();
        $this->assertNotNull($result);

        $hits = $result->getHits();
        $this->assertCount(1, $hits);
        $this->assertInstanceOf(Wall::class, $hits[0]);
    }

}