<?php

namespace Drupal\ecat_mark_up\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for adding ecat xml content types to the request.
 */
class EcatSubscriber implements EventSubscriberInterface {

  /**
   * Register content type formats on the request object.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function onKernelRequest(GetResponseEvent $event) {
    $event->getRequest()->setFormat('eCat_xml', array('text/xml'));
  }

  /**
   * Implements \Symfony\Component\EventDispatcher\EventSubscriberInterface::getSubscribedEvents().
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('onKernelRequest');
    return $events;
  }
}

