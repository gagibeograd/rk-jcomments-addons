<?php
/**
 * Builds the optional administrator template override of the JComments comments list with an extra
 * "Editor's pick" column (a star that toggles plg_jcomments_rkpick through com_ajax and returns to the list).
 *
 * Usage (from the site root, after every JComments update):
 *   php extras/admin-star/make_override.php administrator/components/com_jcomments/views/comments/tmpl/default.php \
 *       administrator/templates/atum/html/com_jcomments/comments/default.php
 *
 * The first argument is the JComments core file (or a copy of it), the second the override to write.
 * The script only inserts three blocks; everything else stays as in the core file.
 *
 * @package   RK add-ons for JComments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

if (PHP_SAPI !== 'cli' || $argc < 3)
{
	fwrite(STDERR, "usage: php make_override.php <core default.php> <override default.php>\n");
	exit(1);
}

$src = $argv[1];
$dst = $argv[2];
$s   = str_replace("\r\n", "\n", (string) file_get_contents($src));

$head = '<?php
/**
 * RK add-ons for JComments: OPTIONAL administrator template override of the JComments comments list.
 * Adds an "Editor\'s pick" column (a star). Everything else is the core file
 * administrator/components/com_jcomments/views/comments/tmpl/default.php; rebuild it after a JComments
 * update with extras/admin-star/make_override.php. The star calls plg_jcomments_rkpick (com_ajax) and returns to the list.
 */';
$s = preg_replace('#^<\?php\s*/\*\*.*?\*/#s', $head, $s, 1, $c0);

// data: picked ids + toggle url
$s = str_replace(
	"\$listDirection  = \$this->escape(\$this->state->get('list.direction'));\n?>",
	"\$listDirection  = \$this->escape(\$this->state->get('list.direction'));\n\n"
	. "// RK add-ons: editor's picks (plg_jcomments_rkpick)\n"
	. "Factory::getApplication()->getLanguage()->load('plg_jcomments_rkpick', JPATH_ADMINISTRATOR);\n"
	. "\$rkPicked  = [];\n"
	. "\$rkCanPick = \$user->authorise('comment.publish', 'com_jcomments');\n"
	. "try {\n"
	. "\t\$rkDb  = Factory::getContainer()->get('DatabaseDriver');\n"
	. "\t\$rkIds = array_map(static fn(\$i) => (int) \$i->id, (array) \$this->items);\n"
	. "\tif (\$rkIds) {\n"
	. "\t\t\$rkPicked = array_map('intval', \$rkDb->setQuery('SELECT comment_id FROM ' . \$rkDb->quoteName('#__jcomments_rkpick') . ' WHERE comment_id IN (' . implode(',', \$rkIds) . ')')->loadColumn() ?: []);\n"
	. "\t}\n"
	. "} catch (\\Throwable \$e) {\n"
	. "}\n"
	. "\$rkReturn  = base64_encode((string) \\Joomla\\CMS\\Uri\\Uri::getInstance());\n"
	. "\$rkPickUrl = static fn(int \$id) => \\Joomla\\CMS\\Uri\\Uri::root(true) . '/index.php?option=com_ajax&group=jcomments&plugin=rkpick&format=raw&id=' . \$id . '&return=' . \$rkReturn;\n"
	. "\$rkBadge   = htmlspecialchars(Text::_('PLG_JCOMMENTS_RKPICK_BADGE'), ENT_QUOTES);\n"
	. "?>",
	$s,
	$c1
);

// header: after the "published" column (first <th ... w-5 text-center)
$s = preg_replace(
	'#(<th scope="col" class="w-5 text-center">.*?</th>)#s',
	"$1\n\t\t\t\t\t\t\t<th scope=\"col\" class=\"w-1 text-center\" title=\"<?php echo \$rkBadge; ?>\"><span class=\"icon-star\" aria-hidden=\"true\"></span><span class=\"visually-hidden\"><?php echo \$rkBadge; ?></span></th>",
	$s,
	1,
	$c2
);

// cell: after the jgrid.published cell
$s = preg_replace(
	'#(<td class="small text-center">\s*<\?php echo HTMLHelper::_\(\'jgrid\.published\'.*?</td>)#s',
	"$1\n\t\t\t\t\t\t\t\t<td class=\"text-center\">\n"
	. "\t\t\t\t\t\t\t\t\t<?php \$rkOn = in_array((int) \$item->id, \$rkPicked, true); ?>\n"
	. "\t\t\t\t\t\t\t\t\t<?php if (\$rkCanPick): ?>\n"
	. "\t\t\t\t\t\t\t\t\t\t<a href=\"<?php echo \$rkPickUrl((int) \$item->id); ?>\" class=\"tbody-icon<?php echo \$rkOn ? ' active' : ''; ?>\" title=\"<?php echo htmlspecialchars(Text::_(\$rkOn ? 'PLG_JCOMMENTS_RKPICK_UNPICK' : 'PLG_JCOMMENTS_RKPICK_PICK'), ENT_QUOTES); ?>\" style=\"color:<?php echo \$rkOn ? '#d9a900' : '#adb5bd'; ?>\"><span class=\"<?php echo \$rkOn ? 'icon-featured' : 'icon-unfeatured'; ?>\" aria-hidden=\"true\"></span></a>\n"
	. "\t\t\t\t\t\t\t\t\t<?php elseif (\$rkOn): ?>\n"
	. "\t\t\t\t\t\t\t\t\t\t<span class=\"icon-featured\" style=\"color:#d9a900\" aria-hidden=\"true\" title=\"<?php echo \$rkBadge; ?>\"></span>\n"
	. "\t\t\t\t\t\t\t\t\t<?php endif; ?>\n"
	. "\t\t\t\t\t\t\t\t</td>",
	$s,
	1,
	$c3
);

if (!$c0 || !$c1 || !$c2 || !$c3)
{
	fwrite(STDERR, "anchors not found (header $c0, data $c1, column $c2, cell $c3): the JComments file changed, adjust the script\n");
	exit(1);
}

@mkdir(dirname($dst), 0777, true);
file_put_contents($dst, $s);
echo "override written: $dst\n";
