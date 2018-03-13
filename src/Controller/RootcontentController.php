<?php

declare(strict_types=1);

namespace Terminal42\RootcontentBundle\Controller;

use Contao\ArticleModel;
use Contao\BackendTemplate;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\Token\FrontendPreviewToken;
use Contao\Date;
use Contao\ModuleArticle;
use Contao\ModuleModel;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RootcontentController
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * @var Connection
     */
    private $database;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     * @param ScopeMatcher             $scopeMatcher
     * @param Connection               $database
     * @param TokenStorageInterface    $tokenStorage
     */
    public function __construct(ContaoFrameworkInterface $framework, ScopeMatcher $scopeMatcher, Connection $database, TokenStorageInterface $tokenStorage)
    {
        $this->framework = $framework;
        $this->scopeMatcher = $scopeMatcher;
        $this->database = $database;
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke(Request $request, ModuleModel $module, string $section)
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            $template = new BackendTemplate('be_wildcard');

            $template->wildcard = '### ROOT CONTENT ###';
            $template->id = $module->id;
            $template->link = $module->name;
            $template->href = $request->getBaseUrl().'?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $module->id;

            return $template->getResponse();
        }

        $this->framework->initialize();

        /** @var PageModel $objPage */
        global $objPage;

        $article = $this->getArticle($objPage->roodId, $module->rootcontent);

        if (null === $article) {
            return '';
        }

        $module = $this->framework->createInstance(ModuleArticle::class, [$article, $section]);

        return new Response($module->generate(true));
    }

    private function getArticle($rootPageId, $section): ?ArticleModel
    {
        /** @var ArticleModel $repository */
        $repository = $this->framework->getAdapter(ArticleModel::class);

        $cols = ['tl_article.pid=?', 'tl_article.title=?'];

        $token = $this->tokenStorage->getToken();

        if (!$token instanceof FrontendPreviewToken || !$token->showUnpublished()) {
            $time = Date::floorToMinute();
            $cols[] = "published='1' AND (start='' OR start<$time) AND (stop='' OR stop>$time)";
        }

        return $repository->findOneBy(
            $cols,
            [$rootPageId, $section]
        );
    }
}

