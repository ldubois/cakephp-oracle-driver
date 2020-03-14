<?php
/**
 * Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2016, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\OracleDriver\Test\TestCase\ORM;

use Cake\Test\TestCase\ORM\RulesCheckerIntegrationTest as CakeRulesCheckerIntegrationTest;

/**
 * Tests RulesCheckerIntegration class
 *
 */
class RulesCheckerIntegrationTest extends CakeRulesCheckerIntegrationTest
{
    /**
     * Fixtures to be loaded
     *
     * @var array
     */
    public $fixtures = [
        'core.Articles',
//        'core.ArticlesTags',
        'plugin.CakeDC/OracleDriver.ArticlesTags',
        'core.Authors',
        'core.Comments',
        'core.Tags',
        'core.SpecialTags',
        'core.Categories',
        'core.SiteArticles',
        'core.SiteAuthors',
        'core.Comments',
    ];
}
