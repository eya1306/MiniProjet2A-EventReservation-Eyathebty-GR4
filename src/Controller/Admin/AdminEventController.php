<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin')]
class AdminEventController extends AbstractController
{
    // ─── Dashboard ───────────────────────────────────────────────────────────
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(EventRepository $eventRepo, ReservationRepository $reservationRepo): Response
    {
        $events = $eventRepo->findAll();
        $totalReservations = 0;
        foreach ($events as $event) {
            $totalReservations += $reservationRepo->countByEvent($event);
        }

        return $this->render('admin/dashboard.html.twig', [
            'events'            => $events,
            'totalReservations' => $totalReservations,
        ]);
    }

    // ─── Create Event ────────────────────────────────────────────────────────
    #[Route('/events/create', name: 'admin_event_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $error = null;

        if ($request->isMethod('POST')) {
            [$event, $error] = $this->buildEventFromRequest($request, $em, $slugger);
            if (!$error) {
                $em->persist($event);
                $em->flush();
                $this->addFlash('success', 'Event created successfully!');
                return $this->redirectToRoute('admin_dashboard');
            }
        }

        return $this->render('admin/event_form.html.twig', [
            'event' => null,
            'error' => $error,
            'action' => $this->generateUrl('admin_event_create'),
        ]);
    }

    // ─── Edit Event ──────────────────────────────────────────────────────────
    #[Route('/events/{id}/edit', name: 'admin_event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $error = null;

        if ($request->isMethod('POST')) {
            [$updatedEvent, $error] = $this->buildEventFromRequest($request, $em, $slugger, $event);
            if (!$error) {
                $em->flush();
                $this->addFlash('success', 'Event updated successfully!');
                return $this->redirectToRoute('admin_dashboard');
            }
        }

        return $this->render('admin/event_form.html.twig', [
            'event'  => $event,
            'error'  => $error,
            'action' => $this->generateUrl('admin_event_edit', ['id' => $event->getId()]),
        ]);
    }

    // ─── Delete Event ────────────────────────────────────────────────────────
    #[Route('/events/{id}/delete', name: 'admin_event_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Event $event, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->request->get('_token'))) {
            // Remove image file if exists
            if ($event->getImage()) {
                $imagePath = $this->getParameter('kernel.project_dir').'/public/uploads/events/'.$event->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $em->remove($event);
            $em->flush();
            $this->addFlash('success', 'Event deleted.');
        }
        return $this->redirectToRoute('admin_dashboard');
    }

    // ─── View Reservations for an Event ─────────────────────────────────────
    #[Route('/events/{id}/reservations', name: 'admin_event_reservations', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function reservations(Event $event, ReservationRepository $reservationRepo): Response
    {
        return $this->render('admin/reservations.html.twig', [
            'event'        => $event,
            'reservations' => $reservationRepo->findByEvent($event),
        ]);
    }

    // ─── Helper: build Event from POST data ──────────────────────────────────
    private function buildEventFromRequest(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ?Event $event = null
    ): array {
        $event ??= new Event();
        $error = null;

        $title       = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));
        $dateStr     = $request->request->get('date', '');
        $location    = trim($request->request->get('location', ''));
        $seats       = (int) $request->request->get('seats', 0);

        if (empty($title) || empty($description) || empty($dateStr) || empty($location) || $seats <= 0) {
            return [$event, 'All fields are required and seats must be greater than 0.'];
        }

        try {
            $date = new \DateTime($dateStr);
        } catch (\Exception) {
            return [$event, 'Invalid date format.'];
        }

        $event->setTitle($title)
              ->setDescription($description)
              ->setDate($date)
              ->setLocation($location)
              ->setSeats($seats);

        // Handle image upload
        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename     = $slugger->slug($originalFilename);
            $newFilename      = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('kernel.project_dir').'/public/uploads/events',
                    $newFilename
                );
                $event->setImage($newFilename);
            } catch (FileException $e) {
                $error = 'Image upload failed: '.$e->getMessage();
            }
        }

        return [$event, $error];
    }
}