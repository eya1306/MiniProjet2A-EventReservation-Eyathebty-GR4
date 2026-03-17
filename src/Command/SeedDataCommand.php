<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed',
    description: 'Seeds the database with demo data',
)]
class SeedDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding database with demo data...');

        // ── Admin ────────────────────────────────────────────────────────────
        $admin = new Admin();
        $admin->setUsername('admin');
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $this->em->persist($admin);
        $io->text('✔ Admin created  →  username: admin  |  password: admin123');

        // ── Users ────────────────────────────────────────────────────────────
        $usersData = [
            ['alice',   'alice@example.com',   'Alice123!'],
            ['bob',     'bob@example.com',     'Bob123!'],
            ['charlie', 'charlie@example.com', 'Charlie123!'],
        ];

        $users = [];
        foreach ($usersData as [$username, , $plain]) {
            $user = new User();
            $user->setUsername($username);
            $user->setPassword($this->hasher->hashPassword($user, $plain));
            $this->em->persist($user);
            $users[] = $user;
            $io->text("✔ User created   →  username: $username  |  password: $plain");
        }

        // ── Events ───────────────────────────────────────────────────────────
        $eventsData = [
            [
                'title'       => 'Tech Summit 2026',
                'description' => 'A full-day conference covering AI, cloud computing, and the future of software development. Featuring keynotes from industry leaders and hands-on workshops.',
                'date'        => new \DateTime('+10 days'),
                'location'    => 'Tunis, Palais des Congrès',
                'seats'       => 200,
            ],
            [
                'title'       => 'Laravel & Symfony Workshop',
                'description' => 'An intensive hands-on workshop for PHP developers. Build a real-world API from scratch using Symfony 7 and modern best practices.',
                'date'        => new \DateTime('+15 days'),
                'location'    => 'Sfax, Centre Culturel',
                'seats'       => 50,
            ],
            [
                'title'       => 'Open Source Day',
                'description' => 'Celebrate the spirit of open source! Join contributors from across the country for talks, code sprints, and networking.',
                'date'        => new \DateTime('+20 days'),
                'location'    => 'Sousse, Hôtel Marhaba',
                'seats'       => 150,
            ],
            [
                'title'       => 'UX & Design Thinking Bootcamp',
                'description' => 'A two-day immersive bootcamp on user experience design. Learn prototyping, user research, and how to build products people love.',
                'date'        => new \DateTime('+25 days'),
                'location'    => 'Tunis, Hub Numérique',
                'seats'       => 40,
            ],
            [
                'title'       => 'DevOps & Cloud Native Meetup',
                'description' => 'Monthly meetup for DevOps engineers and SREs. This month: Kubernetes best practices, GitOps workflows, and live demos.',
                'date'        => new \DateTime('+5 days'),
                'location'    => 'Online (Zoom)',
                'seats'       => 500,
            ],
            [
                'title'       => 'Startup Pitch Night',
                'description' => 'Watch 10 early-stage startups pitch their ideas to a panel of investors and mentors. Networking reception to follow.',
                'date'        => new \DateTime('+30 days'),
                'location'    => 'Lac 2, Business District',
                'seats'       => 100,
            ],
        ];

        $events = [];
        foreach ($eventsData as $data) {
            $event = new Event();
            $event->setTitle($data['title']);
            $event->setDescription($data['description']);
            $event->setDate($data['date']);
            $event->setLocation($data['location']);
            $event->setSeats($data['seats']);
            $this->em->persist($event);
            $events[] = $event;
            $io->text("✔ Event created  →  {$data['title']}");
        }

        // ── Flush so IDs are assigned ─────────────────────────────────────────
        $this->em->flush();

        // ── Reservations ──────────────────────────────────────────────────────
        $reservationsData = [
            ['user' => $users[0], 'event' => $events[0], 'name' => 'Alice Martin',   'email' => 'alice@example.com',   'phone' => '+216 22 111 222'],
            ['user' => $users[1], 'event' => $events[0], 'name' => 'Bob Johnson',    'email' => 'bob@example.com',     'phone' => '+216 55 333 444'],
            ['user' => $users[0], 'event' => $events[1], 'name' => 'Alice Martin',   'email' => 'alice@example.com',   'phone' => '+216 22 111 222'],
            ['user' => $users[2], 'event' => $events[2], 'name' => 'Charlie Dupont', 'email' => 'charlie@example.com', 'phone' => '+216 98 555 666'],
        ];

        foreach ($reservationsData as $data) {
            $reservation = new Reservation();
            $reservation->setUser($data['user']);
            $reservation->setEvent($data['event']);
            $reservation->setName($data['name']);
            $reservation->setEmail($data['email']);
            $reservation->setPhone($data['phone']);
            $this->em->persist($reservation);
            $io->text("✔ Reservation    →  {$data['name']} → {$data['event']->getTitle()}");
        }

        $this->em->flush();

        $io->success('Database seeded successfully!');
        $io->table(
            ['Role', 'Username', 'Password'],
            [
                ['Admin', 'admin',   'admin123'],
                ['User',  'alice',   'Alice123!'],
                ['User',  'bob',     'Bob123!'],
                ['User',  'charlie', 'Charlie123!'],
            ]
        );

        return Command::SUCCESS;
    }
}
