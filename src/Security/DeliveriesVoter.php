<?php

namespace AppBundle\Security;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Webmozart\Assert\Assert;

class DeliveriesVoter extends Voter
{
    const CREATE = 'create';

    private static $actions = [
        self::CREATE,
    ];

    private $authorizationChecker;
    private $storeExtractor;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, TokenStoreExtractor $storeExtractor)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->storeExtractor = $storeExtractor;
    }

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof Delivery) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if ($token instanceof JWTUserToken) {

            $user = $token->getUser();
            $store = $subject->getStore();

            if ($store && is_object($user) && is_callable([ $user, 'ownsStore' ]) && $user->ownsStore($store)) {
                return true;
            }

        } else {

            $roles = $token->getRoles();
            foreach ($roles as $role) {
                if ($role->getRole() === 'ROLE_OAUTH2_DELIVERIES') {

                    $store = $this->storeExtractor->extractStore();

                    if (null === $subject->getStore()) {
                        return true;
                    }

                    if ($subject->getStore() === $store) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}