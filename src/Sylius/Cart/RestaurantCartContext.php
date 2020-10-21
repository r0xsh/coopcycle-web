<?php

namespace AppBundle\Sylius\Cart;

use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityNotFoundException;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class RestaurantCartContext implements CartContextInterface
{
    private $session;

    private $orderRepository;

    private $orderFactory;

    private $restaurantRepository;

    private $sessionKeyName;

    /**
     * @var ChannelContextInterface
     */
    private ChannelContextInterface $channelContext;

    /** @var OrderInterface|null */
    private $cart;

    /**
     * @param SessionInterface $session
     * @param OrderRepositoryInterface $orderRepository
     * @param string $sessionKeyName
     */
    public function __construct(
        SessionInterface $session,
        OrderRepositoryInterface $orderRepository,
        FactoryInterface $orderFactory,
        LocalBusinessRepository $restaurantRepository,
        string $sessionKeyName,
        ChannelContextInterface $channelContext,
        RestaurantResolver $resolver)
    {
        $this->session = $session;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->restaurantRepository = $restaurantRepository;
        $this->sessionKeyName = $sessionKeyName;
        $this->channelContext = $channelContext;
        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function getCart(): BaseOrderInterface
    {
        if (null !== $this->cart) {
            return $this->cart;
        }

        $cart = null;

        if ($this->session->has($this->sessionKeyName)) {
            $cart = $this->orderRepository->findCartById($this->session->get($this->sessionKeyName));

            if (null === $cart || $cart->getChannel()->getCode() !== $this->channelContext->getChannel()->getCode()) {
                $this->session->remove($this->sessionKeyName);
            } else {
                try {
                    // We need to call a method on the restaurant object
                    // so that Doctrine eventually triggers EntityNotFoundException
                    $restaurant = $cart->getRestaurant()->getName();
                } catch (EntityNotFoundException $e) {
                    $cart = null;
                    $this->session->remove($this->sessionKeyName);
                }
            }
        }

        if (null === $cart) {

            $restaurantId = $this->resolver->resolve();

            if (null === $restaurantId) {

                throw new CartNotFoundException('No restaurant could be resolved from request');
            }

            $restaurant = $this->restaurantRepository->find($restaurantId);

            if (null === $restaurant) {

                throw new CartNotFoundException('Restaurant does not exist');
            }

            $cart = $this->orderFactory->createForRestaurant($restaurant);
        }

        $this->cart = $cart;

        return $cart;
    }
}
