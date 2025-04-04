<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\BookModel;
use App\model\ChainModel;
use App\model\HighlightModel;
use App\model\LogModel;
use App\util\markdown\Markdown;
use App\util\VersionDiffUtil;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\StatusCode;
use Jfcherng\Diff\DiffHelper;

class LogController extends Controller
{
    private $logModel;
    private $bookModel;
    private $bookmarkModel;
    private $highlightModel;
    private $chainModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->logModel = new LogModel($container);
        $this->bookModel = new BookModel($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->highlightModel = new HighlightModel($container);
        $this->chainModel = new ChainModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $markdownClient = new Markdown();
        $queryParams = $request->getQueryParams();

        if (isset($queryParams['limit'])) {

            $limit = (int)htmlspecialchars($queryParams['limit']);

            if ($limit > 100) {
                $limit = 100;
            }

        } else {
            $limit = 7;
        }

        $data['pageTitle'] = "Logs | trackr";
        $today = date('Y-m-d', time());

        $todayLog = $this->logModel->getLog($today);

        if ($todayLog) {
            $_SESSION['logs']['todays_log'] = $todayLog['log'];
        } else {
            $this->logModel->insert($today, null);
        }

        $chains = $this->chainModel->getChainsByShowInLogs(1);

        $logs = $this->logModel->getLogs($limit);
        foreach ($logs as $key => $log) {
            $additionalData = '';
            $from = strtotime($log['date']);
            $to = strtotime($log['date']) + 86400;
            $reading = $this->bookModel->getDailyReadingAmount($log['date']);

            $additionalData .= "\n";

            $bookmarks = $this->bookmarkModel->getFinishedBookmarks($from, $to);
            if ($bookmarks) {
                $additionalData .= "**Bookmarks**\n";
                foreach ($bookmarks as $bookmark) {
                    $additionalData .= "- [{$bookmark['title']}]({$bookmark['bookmark']})\n";
                }
            }

            $additionalData .= "\n";

            $highlights = $this->highlightModel->getHighlightsByDateRange($from, $to);

            if ($highlights) {
                $additionalData .= "**Highlights**\n";
                foreach ($highlights as $highlight) {
                    $additionalData .= "- [#{$highlight['id']}](/highlights?id={$highlight['id']})\n";
                }
            }

            $additionalData .= "\n";
            $additionalData .= "**Chains**\n";

            if ($reading) {
                $additionalData .= "- [x] Reading: $reading\n";
            } else {
                $additionalData .= "- [ ] Reading: $reading\n";
            }

            if ($chains) {
                foreach ($chains as $chain) {
                    $link = $this->chainModel->getLinkByChainIdAndDate($chain['chainId'], $log['date']);

                    if ($link) {
                        $additionalData .= "{$link['linkValueShowInLogsValue']} {$chain['chainName']}\n";
                    } else {
                        $additionalData .= "- [ ] {$chain['chainName']}\n";
                    }
                }
            }

            $additionalData = $markdownClient->convert($additionalData);
            $logs[$key]['additionalData'] = $additionalData;
        }

        $data['logs'] = $logs;
        $data['todaysLog'] = $todayLog['log'];
        $data['today'] = $today;
        $data['activeLogs'] = 'active';

        return $this->view->render($response, 'logs/index.mustache', $data);
    }

    public function logsVersions(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $date = htmlspecialchars($args['date']);
        $versionDiffs = [];

        $versions = $this->logModel->getVersionsByDate($date);
        $latest = $this->logModel->getLog($date);
        $newString = $latest['log'];

        $latestDiff = DiffHelper::calculate(
            $newString,
            $newString,
            'Inline',
            VersionDiffUtil::logsDiffOptions(),
            VersionDiffUtil::logsRendererOptions(),
        );
        $versionDiffs[] = ['diff' => $latestDiff, 'created_at' => 'Latest'];

        foreach ($versions as $version) {
            $new = $newString;
            $old = $version['old'];
            $sideBySideResult = DiffHelper::calculate(
                $old,
                $new,
                'Inline',
                VersionDiffUtil::logsDiffOptions(),
                VersionDiffUtil::logsRendererOptions(),
            );

            $versionDiffs[] = ['diff' => $sideBySideResult, 'created_at' => $version['created_at']];
            $newString = $version['old'];
        }

        $resource['data']['versionDiffs'] = $versionDiffs;
        $resource['data']['date'] = $date;
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $today = date('Y-m-d', time());

        if (!$params['log']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Log cannot be null!");
        }

        $todaysLog = $this->logModel->getLog($today);

        if ($todaysLog) {

            $previousLog = $_SESSION['logs']['todays_log'] ?? null;

            if ($params['log'] !== $previousLog) {
                $this->logModel->update($today, $params['log']);

                if ($todaysLog['log']) {
                    $this->logModel->saveOldVersion($todaysLog['id'], $todaysLog['log']);
                }
                $resource['message'] = "Saved successfully";
            } else {
                $resource['message'] = "Did not save. Logs are equal";
            }

        } else {
            // while saving the log, date might change
            $this->logModel->insert($today, $params['log']);
        }

        $_SESSION['logs']['todays_log'] = $todaysLog['log'];

        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

}