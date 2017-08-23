<?php

use Bolt\Requirement\BoltRequirements;

if (!isset($_SERVER['HTTP_HOST'])) {
    exit("This script cannot be run from the CLI. Run it from a browser.\n");
}

if (!in_array(@$_SERVER['REMOTE_ADDR'], array(
    '127.0.0.1',
    '::1',
))) {
    header('HTTP/1.0 403 Forbidden');
    exit('This script is only accessible from localhost.');
}

if (file_exists($autoloader = __DIR__.'/../../../autoload.php')) {
    require_once $autoloader;
} elseif (file_exists($autoloader = __DIR__.'/../vendor/autoload.php')) {
    require_once $autoloader;
} else {
    throw new \RuntimeException('Unable to find the Composer autoloader.');
}

$boltRequirements = new BoltRequirements(dirname(dirname(realpath($autoloader))), 3.3);

$majorProblems = $boltRequirements->getFailedRequirements();
$minorProblems = $boltRequirements->getFailedRecommendations();
$hasMajorProblems = (bool) count($majorProblems);
$hasMinorProblems = (bool) count($minorProblems);

?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="robots" content="noindex,nofollow" />
    <title>Bolt Configuration Checker</title>
    <style><?php echo file_get_contents(__DIR__ . '/style.css') ?></style>
</head>
<body>
<div id="content">
    <div class="header clear-fix">
        <div class="header-logo">
            <img src="data:image/png;base64,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" alt="Bolt" />
        </div>

        <div class="search">
            <form method="get" action="http://docs.bolt.cm/search">
                <div class="form-row">

                    <label for="search-id">
                        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAABUElEQVQoz2NgAIJ29iBdD0d7X2cPb+tY2f9MDMjgP2O2hKu7vS8CBlisZUNSMJ3fxRMkXO61wm2ue6I3iB1q8Z8ZriDZFCS03fm/wX+1/xp/TBo8QPxeqf+MUAW+QIFKj/+q/wX/c/3n/i/6Qd/bx943z/Q/K1SBI1D9fKv/AhCn/Wf5L5EHdFGKw39OqAIXoPpOMziX4T9/DFBBnuN/HqhAEtCKCNf/XDA/rZRyAmrpsvrPDVUw3wrkqCiLaewg6TohX1d7X0ffs5r/OaAKfinmgt3t4ulr4+Xg4ANip3j+l/zPArNT4LNOD0pAgWCSOUIBy3+h/+pXbBa5tni0eMx23+/mB1YSYnENroT5Pw/QSOX/mkCo+l/jgo0v2KJA643s8PgAmsMBDCbu/5xALHPB2husxN9uCzsDOgAq5kAoaZVnYMCh5Ky1r88Eh/+iABM8jUk7ClYIAAAAAElFTkSuQmCC" alt="Search on Bolt's Documentation website" />
                    </label>

                    <input name="q" id="search-id" type="search" placeholder="Search on Bolt's documentation website" />

                    <button type="submit" class="sf-button">
                          <span class="border-l">
                            <span class="border-r">
                                <span class="btn-bg">OK</span>
                            </span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="sf-reset">
        <div class="block">
            <div class="bolt-block-content">
                <h1 class="title">Configuration Checker</h1>
                <p>This script analyzes your system to check whether is ready to run Bolt.</p>

                <?php if ($hasMajorProblems): ?>
                    <h2 class="ko">Major problems</h2>
                    <p>Major problems have been detected and <strong>must</strong> be fixed before continuing:</p>
                    <ol>
                        <?php foreach ($majorProblems as $problem): ?>
                            <li><?php echo $problem->getTestMessage() ?>
                                <p class="help"><em><?php echo $problem->getHelpHtml() ?></em></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if ($hasMinorProblems): ?>
                    <h2>Recommendations</h2>
                    <p>
                    <?php if ($hasMajorProblems): ?>Additionally, to<?php else: ?>To<?php endif; ?> enhance your Bolt experience,
                    it’s recommended that you fix the following:
                    </p>
                    <ol>
                        <?php foreach ($minorProblems as $problem): ?>
                            <li><?php echo $problem->getTestMessage() ?>
                                <p class="help"><em><?php echo $problem->getHelpHtml() ?></em></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if ($boltRequirements->hasPhpConfigIssue()): ?>
                    <p id="phpini">*
                    <?php if ($boltRequirements->getPhpIniPath()): ?>
                        Changes to the <strong>php.ini</strong> file must be done in "<strong><?php echo $boltRequirements->getPhpIniPath() ?></strong>".
                    <?php else: ?>
                        To change settings, create a "<strong>php.ini</strong>".
                    <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if (!$hasMajorProblems && !$hasMinorProblems): ?>
                    <p class="ok">All checks passed successfully. Your system is ready to run Bolt.</p>
                <?php endif; ?>

                <ul class="bolt-install-continue">
                    <?php if ($hasMajorProblems || $hasMinorProblems): ?>
                        <li><a href="config.php">Re-check configuration</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>
