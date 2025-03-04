<?php

/*
 * This file is part of the HWIOAuthBundle package.
 *
 * (c) Hardware Info <opensource@hardware.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HWI\Bundle\OAuthBundle\Tests\Controller;

use HWI\Bundle\OAuthBundle\Connect\AccountConnectorInterface;
use HWI\Bundle\OAuthBundle\Controller\ConnectController;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;
use HWI\Bundle\OAuthBundle\Security\Core\Exception\AccountNotLinkedException;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMap;
use HWI\Bundle\OAuthBundle\Security\Http\ResourceOwnerMapLocator;
use HWI\Bundle\OAuthBundle\Security\OAuthUtils;
use HWI\Bundle\OAuthBundle\Tests\Fixtures\CustomOAuthToken;
use HWI\Bundle\OAuthBundle\Tests\Fixtures\CustomUserResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Templating\EngineInterface;

abstract class AbstractConnectControllerTest extends TestCase
{
    /**
     * @var MockObject&AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var MockObject&TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var MockObject&EngineInterface
     */
    protected $twig;

    /**
     * @var MockObject&RouterInterface
     */
    protected $router;

    /**
     * @var MockObject&ResourceOwnerMap
     */
    protected $resourceOwnerMap;

    /**
     * @var MockObject&ResourceOwnerInterface
     */
    protected $resourceOwner;

    /**
     * @var MockObject&AccountConnectorInterface
     */
    protected $accountConnector;

    /**
     * @var MockObject&OAuthUtils
     */
    protected $oAuthUtils;

    /**
     * @var ResourceOwnerMapLocator
     */
    protected $resourceOwnerMapLocator;

    /**
     * @var MockObject&UserCheckerInterface
     */
    protected $userChecker;

    /**
     * @var MockObject&EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var MockObject&FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var MockObject&SessionInterface
     */
    protected $session;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var ContainerInterface
     */
    protected $container;

    protected function setUp(): void
    {
        parent::setUp();

        $bag = new EnvPlaceholderParameterBag(
            [
                'hwi_oauth.connect' => true,
                'hwi_oauth.firewall_names' => ['default'],
                'hwi_oauth.connect.confirmation' => true,
                'hwi_oauth.grant_rule' => 'IS_AUTHENTICATED_REMEMBERED',
            ]
        );

        $this->container = new Container($bag);
        $this->container->set('parameter_bag', $bag);

        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->container->set('security.authorization_checker', $this->authorizationChecker);

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->container->set('security.token_storage', $this->tokenStorage);

        $this->twig = $this->createMock(EngineInterface::class);
        $this->twig->expects($this->any())
            ->method('render')
            ->willReturn('')
        ;
        $this->container->set('twig', $this->twig);

        $this->router = $this->createMock(RouterInterface::class);
        $this->container->set('router', $this->router);

        $this->resourceOwner = $this->createMock(ResourceOwnerInterface::class);
        $this->resourceOwner->expects($this->any())
            ->method('getUserInformation')
            ->willReturn(new CustomUserResponse())
        ;
        $this->resourceOwnerMap = $this->createMock(ResourceOwnerMap::class);
        $this->resourceOwnerMap->expects($this->any())
            ->method('getResourceOwnerByName')
            ->withAnyParameters()
            ->willReturn($this->resourceOwner);
        $this->container->set('hwi_oauth.resource_ownermap.default', $this->resourceOwnerMap);

        $this->accountConnector = $this->createMock(AccountConnectorInterface::class);
        $this->container->set('hwi_oauth.account.connector', $this->accountConnector);

        $this->oAuthUtils = $this->createMock(OAuthUtils::class);

        $this->resourceOwnerMapLocator = new ResourceOwnerMapLocator();
        $this->resourceOwnerMapLocator->add('default', $this->resourceOwnerMap);

        $this->userChecker = $this->createMock(UserCheckerInterface::class);
        $this->container->set('hwi_oauth.user_checker', $this->userChecker);

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->set('event_dispatcher', $this->eventDispatcher);

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->container->set('form.factory', $this->formFactory);

        $this->session = $this->createMock(SessionInterface::class);
        $this->request = Request::create('/');
        $this->request->setSession($this->session);
    }

    protected function createAccountNotLinkedException(): AccountNotLinkedException
    {
        $accountNotLinked = new AccountNotLinkedException();
        $accountNotLinked->setResourceOwnerName('facebook');
        $accountNotLinked->setToken(new CustomOAuthToken());

        return $accountNotLinked;
    }

    protected function mockAuthorizationCheck(bool $granted = true): void
    {
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('IS_AUTHENTICATED_REMEMBERED')
            ->willReturn($granted)
        ;
    }

    protected function createConnectController(
        bool $connectEnabled = true,
        bool $confirmConnect = true,
        array $firewallNames = ['default']
    ): ConnectController {
        $controller = new ConnectController(
            $this->oAuthUtils,
            $this->resourceOwnerMapLocator,
            $this->createMock(RequestStack::class),
            $connectEnabled,
            'IS_AUTHENTICATED_REMEMBERED',
            true,
            'fake_route',
            $confirmConnect,
            $firewallNames
        );
        $controller->setContainer($this->container);

        return $controller;
    }
}
