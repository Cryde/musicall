/**
 * Mirrors `App\Procedure\BandSpace\TechRiderDuplicateProcedure`, whose constants these are.
 *
 * Duplicated because the duplicate dialog proposes a name in its field rather than letting the
 * server apply its default, so the client has to do the same arithmetic or offer a name the server
 * will refuse. Pinned against the PHP by
 * tests/Unit/Procedure/BandSpace/TechRiderDuplicateNameLimitsTest.php.
 */
export const MAX_NAME_LENGTH = 255

export const COPY_SUFFIX = ' (copie)'
