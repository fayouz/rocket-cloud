<?php

namespace App\Dashboard;

use App\Entity\User;
use App\Files\FileUploader;
use Doctrine\DBAL\Connection;

/**
 * Rocket Cloud on the dashboard: storage used, files added, shares and downloads. A space is private:
 * even administrators see figures, not other people's files.
 */
final class FilesSection implements DashboardSectionInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly FileUploader $uploader,
    ) {
    }

    public function build(User $user, bool $admin, \DateTimeImmutable $from, \DateTimeImmutable $previousFrom): array
    {
        $params = ['user' => $user->getId()->toRfc4122()];
        [$scope, $scopeParams] = $admin ? ['TRUE', []] : ['f.owner_id = :user', $params];

        $totals = $this->db->fetchAssociative("SELECT COUNT(*) AS files, COALESCE(SUM(f.size), 0) AS bytes FROM stored_file f WHERE $scope", $scopeParams);
        $mine = (int) $this->db->fetchOne('SELECT COALESCE(SUM(size), 0) FROM stored_file WHERE owner_id = :user', $params);
        $quota = $this->uploader->quota();

        $daily = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT to_char(date_trunc('day', f.created_at), 'YYYY-MM-DD') AS day, COUNT(*) AS n FROM stored_file f
             WHERE $scope AND f.created_at >= :from GROUP BY 1",
            $scopeParams + ['from' => DashboardStats::sql($from)],
        ) as $row) {
            $daily[$row['day']]['uploads'] = (int) $row['n'];
        }
        $shareScope = $admin ? 'TRUE' : 's.owner_id = :user';
        foreach ($this->db->fetchAllAssociative(
            "SELECT to_char(date_trunc('day', s.created_at), 'YYYY-MM-DD') AS day, COUNT(*) AS n FROM share_link s
             WHERE $shareScope AND s.created_at >= :from GROUP BY 1",
            ($admin ? [] : $params) + ['from' => DashboardStats::sql($from)],
        ) as $row) {
            $daily[$row['day']]['shares'] = (int) $row['n'];
        }
        $previousUploads = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM stored_file f WHERE $scope AND f.created_at >= :from AND f.created_at < :to",
            $scopeParams + ['from' => DashboardStats::sql($previousFrom), 'to' => DashboardStats::sql($from)],
        );
        $shares = $this->db->fetchAssociative(
            "SELECT COUNT(*) AS total, COALESCE(SUM(download_count), 0) AS downloads FROM share_link s WHERE $shareScope",
            $admin ? [] : $params,
        );

        return [
            'kpis' => [
                [
                    'id' => 'storage',
                    'label' => 'Mon espace',
                    'value' => $mine,
                    'format' => 'bytes',
                    'icon' => 'i-lucide-hard-drive',
                    'tone' => 'bg-primary/10 text-primary',
                    'progress' => $quota > 0 ? round(100 * $mine / $quota, 1) : null,
                    'detail' => \sprintf('sur %s', self::bytes($quota)),
                ],
                [
                    'id' => 'uploads',
                    'label' => 'Fichiers ajoutés (30 j)',
                    'value' => array_sum(array_column($daily, 'uploads')),
                    'format' => 'number',
                    'icon' => 'i-lucide-upload',
                    'tone' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
                    'series' => 'uploads',
                    'previous' => $previousUploads,
                ],
                [
                    'id' => 'files',
                    'label' => $admin ? 'Fichiers de la plateforme' : 'Mes fichiers',
                    'value' => (int) $totals['files'],
                    'format' => 'number',
                    'icon' => 'i-lucide-files',
                    'tone' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                    'detail' => self::bytes((int) $totals['bytes']).' au total',
                ],
                [
                    'id' => 'shares',
                    'label' => $admin ? 'Liens de partage' : 'Mes partages',
                    'value' => (int) $shares['total'],
                    'format' => 'number',
                    'icon' => 'i-lucide-share-2',
                    'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                    'detail' => \sprintf('%d téléchargement(s)', $shares['downloads']),
                ],
            ],
            'series' => [
                ['key' => 'uploads', 'label' => 'Fichiers ajoutés', 'color' => 'bg-primary'],
                ['key' => 'shares', 'label' => 'Partages', 'color' => 'bg-amber-500'],
            ],
            'daily' => $daily,
            // Always the user's own files, even for administrators.
            'recent' => [
                'title' => 'Mes derniers fichiers',
                'link' => '/files',
                'empty' => 'Aucun fichier pour le moment : déposez-en dans « Mes fichiers ».',
                'items' => array_map(static fn (array $row) => [
                    'id' => $row['id'],
                    'title' => $row['name'],
                    'subtitle' => self::bytes((int) $row['size']).($row['folder'] ? ' · '.$row['folder'] : ''),
                    'at' => DashboardStats::atom($row['created_at']),
                    'link' => '/files'.($row['folder_id'] ? '?folder='.$row['folder_id'] : ''),
                ], $this->db->fetchAllAssociative(
                    'SELECT f.id, f.name, f.size, f.created_at, f.folder_id, d.name AS folder FROM stored_file f LEFT JOIN folder d ON d.id = f.folder_id
                     WHERE f.owner_id = :user ORDER BY f.created_at DESC LIMIT 6',
                    $params,
                )),
            ],
            'activity' => array_map(static fn (array $row) => [
                'type' => 'share.created',
                'at' => DashboardStats::atom($row['created_at']),
                'title' => $row['name'] ?? 'Élément supprimé',
                'actor' => $row['email'].($row['recipients'] > 0 ? \sprintf(' · %d destinataire(s)', $row['recipients']) : ''),
                'link' => '/shares',
                'icon' => 'i-lucide-share-2',
                'label' => 'Nouveau partage',
                'color' => 'text-amber-600 bg-amber-500/10 dark:text-amber-400',
            ], $this->db->fetchAllAssociative(
                "SELECT s.created_at, COALESCE(f.name, d.name) AS name, u.email, json_array_length(s.recipients::json) AS recipients
                 FROM share_link s JOIN \"user\" u ON u.id = s.owner_id LEFT JOIN stored_file f ON f.id = s.file_id LEFT JOIN folder d ON d.id = s.folder_id
                 WHERE $shareScope ORDER BY s.created_at DESC LIMIT 8",
                $admin ? [] : $params,
            )),
            'quickActions' => [
                ['label' => 'Déposer des fichiers', 'icon' => 'i-lucide-upload', 'to' => '/files?upload=1', 'tone' => 'bg-primary/10 text-primary'],
                ['label' => 'Mes partages', 'icon' => 'i-lucide-share-2', 'to' => '/shares', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
            ],
        ];
    }

    private static function bytes(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < \count($units) - 1) {
            $value /= 1024;
            ++$i;
        }

        return (0 === $i ? (string) $bytes : number_format($value, 1, ',', ' ')).' '.$units[$i];
    }
}
