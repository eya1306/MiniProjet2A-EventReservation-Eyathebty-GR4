<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds the database with:
 *   - 1 default admin  (admin / Admin@1234)
 *   - 3 sample events
 *
 * Run with:  php bin/console doctrine:fixtures:load
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
        )
    {
    }

    public function load(ObjectManager $manager): void
    {
        // ── Default admin ─────────────────────────────────────────
        $admin = new Admin();
        $admin->setUsername('admin');
        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin@1234'));
        $manager->persist($admin);

        // ── Sample events ─────────────────────────────────────────
        $eventsData = [
            [
                'title' => 'Nuit du Jazz — Sousse',
                'description' => 'Une soirée magique avec les meilleurs artistes jazz de la région. Venez vibrer au son de la trompette et du saxophone dans un cadre exceptionnel.',
                'date' => new \DateTime('+7 days 20:00'),
                'location' => 'Théâtre Municipal, Sousse',
                'seats' => 120,
            ],
            [
                'title' => 'Conférence Tech & IA 2026',
                'description' => 'Rejoignez les leaders de l\'industrie tech pour des talks exclusifs sur l\'Intelligence Artificielle, le DevOps et les architectures modernes.',
                'date' => new \DateTime('+14 days 09:00'),
                'location' => 'ISSAT Sousse — Amphithéâtre A',
                'seats' => 300,
            ],
            [
                'title' => 'Festival de la Médina',
                'description' => 'Célébrez l\'art et la culture maghrébine avec des expositions, des ateliers artisanaux et des spectacles de rue tout le week-end.',
                'date' => new \DateTime('+21 days 10:00'),
                'location' => 'Médina de Sousse',
                'seats' => 500,
            ],
        ];

        foreach ($eventsData as $data) {
            $event = new Event();
            $event->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setDate($data['date'])
                ->setLocation($data['location'])
                ->setSeats($data['seats']);
            $manager->persist($event);
        }

        $manager->flush();
    }
}