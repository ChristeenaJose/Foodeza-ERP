<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Services;

final class FileStorage
{
    /** @return array{id: int, path: string} */
    public static function storeUpload(array $file, string $type): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed.');
        }

        $config = require __DIR__ . '/../../config/app.php';
        $subdir = $type === 'receipt' ? 'receipts' : 'invoices';
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

        $mime = mime_content_type($file['tmp_name']) ?: $file['type'];
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Only PDF, JPEG, PNG, and WebP files are allowed.');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
        $stored = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
        $destDir = $config['storage'] . '/' . $subdir;
        $destPath = $destDir . '/' . $stored;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Could not save uploaded file.');
        }

        $pdo = \Foodeza\Bookkeeping\Database::connection();
        $pdo->prepare(
            'INSERT INTO attachments (original_name, stored_name, mime_type, file_size, type)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $file['name'],
            $subdir . '/' . $stored,
            $mime,
            (int) $file['size'],
            $type,
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'path' => $destPath,
        ];
    }

    public static function fullPath(string $storedName): string
    {
        $config = require __DIR__ . '/../../config/app.php';

        return $config['storage'] . '/' . $storedName;
    }
}
