<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/events')]
class EventController extends AbstractController
{
    // ─── List all events ─────────────────────────────────────────────────────
    #[Route('', name: 'app_events_index', methods: ['GET'])]
    public function index(EventRepository $eventRepo): Response
    {
        $events = $eventRepo->findAll();
        usort($events, fn($a, $b) => $a->getDate() <=> $b->getDate());

        return $this->render('events/index.html.twig', ['events' => $events]);
    }

    // ─── Single event detail ─────────────────────────────────────────────────
    #[Route('/{id}', name: 'app_events_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event, ReservationRepository $reservationRepo): Response
    {
        $alreadyReserved = false;
        if ($this->getUser()) {
            $alreadyReserved = $reservationRepo->findOneBy(['event' => $event, 'user' => $this->getUser()]) !== null;
        }

        return $this->render('events/show.html.twig', [
            'event' => $event,
            'alreadyReserved' => $alreadyReserved,
        ]);
    }

    // ─── Reservation form ────────────────────────────────────────────────────
    #[Route('/{id}/reserve', name: 'app_events_reserve', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function reserve(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo,
        MailerInterface $mailer
    ): Response {
        if (!$this->getUser()) {
            $this->addFlash('error', 'You must be logged in to make a reservation.');
            return $this->redirectToRoute('app_login');
        }

        // Block duplicate reservations
        $existing = $reservationRepo->findOneBy(['event' => $event, 'user' => $this->getUser()]);
        if ($existing) {
            $this->addFlash('info', 'Vous avez déjà une réservation pour cet événement.');
            return $this->redirectToRoute('app_events_show', ['id' => $event->getId()]);
        }

        if ($event->getAvailableSeats() <= 0) {
            $this->addFlash('error', 'Sorry, no seats available for this event.');
            return $this->redirectToRoute('app_events_show', ['id' => $event->getId()]);
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $name  = trim($request->request->get('name', ''));
            $email = trim($request->request->get('email', ''));
            $phone = trim($request->request->get('phone', ''));

            if (empty($name) || empty($email) || empty($phone)) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } else {
                $reservation = new Reservation();
                $reservation->setEvent($event);
                $reservation->setUser($this->getUser());
                $reservation->setName($name);
                $reservation->setEmail($email);
                $reservation->setPhone($phone);

                $em->persist($reservation);
                $em->flush();

                // ── Send confirmation email via Mailtrap ──────────────────
                try {
                    $confirmEmail = (new Email())
                        ->from('noreply@eventreserve.com')
                        ->to($email)
                        ->subject('✅ Confirmation de réservation — ' . $event->getTitle())
                        ->html(
                            '<h2>Bonjour ' . htmlspecialchars($name) . ',</h2>' .
                            '<p>Votre réservation pour <strong>' . htmlspecialchars($event->getTitle()) . '</strong> est confirmée !</p>' .
                            '<ul>' .
                            '<li>📅 Date : ' . $event->getDate()->format('d/m/Y à H:i') . '</li>' .
                            '<li>📍 Lieu : ' . htmlspecialchars($event->getLocation()) . '</li>' .
                            '<li>📞 Téléphone : ' . htmlspecialchars($phone) . '</li>' .
                            '</ul>' .
                            '<p>À très bientôt !<br><strong>L\'équipe EventReserve</strong></p>'
                        );
                    $mailer->send($confirmEmail);
                } catch (\Exception $e) {
                    // Email failure should not block the reservation
                }

                return $this->redirectToRoute('app_reservation_confirm', [
                    'id' => $reservation->getId(),
                ]);
            }
        }

        return $this->render('events/reserve.html.twig', [
            'event' => $event,
            'error' => $error,
        ]);
    }

    // ─── My Reservations ─────────────────────────────────────────────────────
    #[Route('/my-reservations', name: 'app_my_reservations', methods: ['GET'])]
    public function myReservations(ReservationRepository $reservationRepo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $reservations = $reservationRepo->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('reservations/my_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    // ─── Cancel reservation ───────────────────────────────────────────────────
    #[Route('/reservations/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancelReservation(
        Reservation $reservation,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('cancel_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Votre réservation a été annulée.');
        }

        return $this->redirectToRoute('app_my_reservations');
    }

    // ─── Confirmation page ───────────────────────────────────────────────────
    #[Route('/confirmation/{id}', name: 'app_reservation_confirm', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function confirm(Reservation $reservation): Response
    {
        // Only the reservation owner can view this
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('reservations/confirm.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}