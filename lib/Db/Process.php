<?php

declare(strict_types=1);

namespace OCA\IntegrationSignd\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string getProcessId()
 * @method void setProcessId(string $processId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string|null getTargetDir()
 * @method void setTargetDir(?string $targetDir)
 * @method string|null getFinishedPdfPath()
 * @method void setFinishedPdfPath(?string $finishedPdfPath)
 */
class Process extends Entity {
    protected int $fileId = 0;
    protected string $processId = '';
    protected string $userId = '';
    protected ?string $targetDir = null;
    protected ?string $finishedPdfPath = null;

    public function __construct() {
        $this->addType('fileId', 'integer');
    }
}
