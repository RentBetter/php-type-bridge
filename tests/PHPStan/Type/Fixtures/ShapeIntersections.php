<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type\Fixtures;

use function PHPStan\Testing\assertType;

/**
 * @phpstan-type BaseData = array{id: string, name?: string}
 * @phpstan-type OptionalBaseData = array{name?: string}
 * @phpstan-type NullableBaseData = array{id: string, note: string|null}
 * @phpstan-type NonEmptyBaseData = non-empty-array{a?: int, b?: int}
 * @phpstan-type OpenData = array{id: string, ...}
 * @phpstan-type OpenIntData = array{id: string, ...<int>}
 *
 * @phpstan-type AdminData = BaseData & array{'$admin': array{email: string}}
 * @phpstan-type OptionalAdminData = BaseData & array{'$admin'?: array{email: string}}
 * @phpstan-type OnOptionalBaseData = OptionalBaseData & array{extra: int}
 * @phpstan-type AuditedAdminData = AdminData & array{audit: list<string>}
 * @phpstan-type OpenOnSealedData = BaseData & array{flag: bool, ...}
 * @phpstan-type NonEmptyExtendedData = NonEmptyBaseData & array{c?: int}
 * @phpstan-type LargeBaseData = array{
 *     a0: int, a1: int, a2: int, a3: int, a4: int, a5: int, a6: int, a7: int, a8: int, a9: int,
 *     a10: int, a11: int, a12: int, a13: int, a14: int, a15: int, a16: int, a17: int, a18: int, a19: int,
 *     a20: int, a21: int, a22: int, a23: int, a24: int, a25: int, a26: int, a27: int, a28: int, a29: int,
 *     a30: int, a31: int, a32: int, a33: int, a34: int, a35: int, a36: int, a37: int, a38: int, a39: int,
 *     a40: int, a41: int, a42: int, a43: int, a44: int, a45: int, a46: int, a47: int, a48: int, a49: int,
 *     a50: int, a51: int, a52: int, a53: int, a54: int, a55: int, a56: int, a57: int, a58: int, a59: int,
 *     a60: int, a61: int, a62: int, a63: int, a64: int, a65: int, a66: int, a67: int, a68: int, a69: int,
 *     a70: int, a71: int, a72: int, a73: int, a74: int, a75: int, a76: int, a77: int, a78: int, a79: int,
 *     a80: int, a81: int, a82: int, a83: int, a84: int, a85: int, a86: int, a87: int, a88: int, a89: int,
 *     a90: int, a91: int, a92: int, a93: int, a94: int, a95: int, a96: int, a97: int, a98: int, a99: int,
 *     a100: int, a101: int, a102: int, a103: int, a104: int, a105: int, a106: int, a107: int, a108: int, a109: int,
 *     a110: int, a111: int, a112: int, a113: int, a114: int, a115: int, a116: int, a117: int, a118: int, a119: int,
 *     a120: int, a121: int, a122: int, a123: int, a124: int, a125: int, a126: int, a127: int, a128: int, a129: int,
 *     a130: int, a131: int, a132: int, a133: int, a134: int, a135: int, a136: int, a137: int, a138: int, a139: int,
 * }
 * @phpstan-type LargeData = LargeBaseData & array{
 *     b0: string, b1: string, b2: string, b3: string, b4: string, b5: string, b6: string, b7: string, b8: string, b9: string,
 *     b10: string, b11: string, b12: string, b13: string, b14: string, b15: string, b16: string, b17: string, b18: string, b19: string,
 *     b20: string, b21: string, b22: string, b23: string, b24: string, b25: string, b26: string, b27: string, b28: string, b29: string,
 *     b30: string, b31: string, b32: string, b33: string, b34: string, b35: string, b36: string, b37: string, b38: string, b39: string,
 *     b40: string, b41: string, b42: string, b43: string, b44: string, b45: string, b46: string, b47: string, b48: string, b49: string,
 *     b50: string, b51: string, b52: string, b53: string, b54: string, b55: string, b56: string, b57: string, b58: string, b59: string,
 *     b60: string, b61: string, b62: string, b63: string, b64: string, b65: string, b66: string, b67: string, b68: string, b69: string,
 *     b70: string, b71: string, b72: string, b73: string, b74: string, b75: string, b76: string, b77: string, b78: string, b79: string,
 *     b80: string, b81: string, b82: string, b83: string, b84: string, b85: string, b86: string, b87: string, b88: string, b89: string,
 *     b90: string, b91: string, b92: string, b93: string, b94: string, b95: string, b96: string, b97: string, b98: string, b99: string,
 *     b100: string, b101: string, b102: string, b103: string, b104: string, b105: string, b106: string, b107: string, b108: string, b109: string,
 *     b110: string, b111: string, b112: string, b113: string, b114: string, b115: string, b116: string, b117: string, b118: string, b119: string,
 *     b120: string, b121: string, b122: string, b123: string, b124: string, b125: string, b126: string, b127: string, b128: string, b129: string,
 *     b130: string, b131: string, b132: string, b133: string, b134: string, b135: string, b136: string, b137: string, b138: string, b139: string,
 * }
 *
 * @phpstan-type RedeclaredData = BaseData & array{id: string, extra: int}
 * @phpstan-type RequiredNameData = BaseData & array{name: string}
 * @phpstan-type NarrowedNoteData = NullableBaseData & array{note: string}
 * @phpstan-type ClashData = BaseData & array{id: int}
 * @phpstan-type LoosenedData = BaseData & array{id?: string}
 *
 * @phpstan-type OpenExtendedData = OpenData & array{flag: bool, ...}
 * @phpstan-type OpenClashData = OpenIntData & array{flag: bool, ...<int>}
 * @phpstan-type SealedOnOpenData = OpenData & array{flag: bool}
 * @phpstan-type ReversedData = array{extra: int} & BaseData
 * @phpstan-type ThreePartData = BaseData & array{a: int} & array{b: int}
 * @phpstan-type TupleData = BaseData & array{int, int}
 */
final class ShapeIntersections
{
    /**
     * @param AdminData $admin
     * @param OptionalAdminData $optionalAdmin
     * @param OnOptionalBaseData $onOptionalBase
     * @param AuditedAdminData $auditedAdmin
     * @param OpenOnSealedData $openOnSealed
     * @param NonEmptyExtendedData $nonEmptyExtended
     * @param LargeData $large
     */
    public function merged(
        array $admin,
        array $optionalAdmin,
        array $onOptionalBase,
        array $auditedAdmin,
        array $openOnSealed,
        array $nonEmptyExtended,
        array $large,
    ): void {
        assertType("array{id: string, name?: string, '\$admin': array{email: string}}", $admin);
        assertType("array{id: string, name?: string, '\$admin'?: array{email: string}}", $optionalAdmin);
        assertType('array{name?: string, extra: int}', $onOptionalBase);
        assertType("array{id: string, name?: string, '\$admin': array{email: string}, audit: list<string>}", $auditedAdmin);
        assertType('array{id: string, name?: string, flag: bool, ...}', $openOnSealed);
        assertType('non-empty-array{a?: int, b?: int, c?: int}', $nonEmptyExtended);
        // As wide as it is, the merged shape keeps every key and its type
        assertType('int', $large['a0']);
        assertType('string', $large['b139']);
    }

    /**
     * A key the base declares may be declared again to narrow it, as an interface extending
     * another may: to a narrower type, or required in place of optional.
     *
     * @param RedeclaredData $redeclared
     * @param RequiredNameData $requiredName
     * @param NarrowedNoteData $narrowedNote
     */
    public function narrowed(array $redeclared, array $requiredName, array $narrowedNote): void
    {
        assertType('array{id: string, name?: string, extra: int}', $redeclared);
        assertType('array{id: string, name: string}', $requiredName);
        assertType('array{id: string, note: string}', $narrowedNote);
    }

    /**
     * Any other redeclaration is an invalid type, so each is its native type.
     *
     * @param ClashData $clash
     * @param LoosenedData $loosened
     */
    public function redeclaredAsSomethingElse(array $clash, array $loosened): void
    {
        assertType('array', $clash);
        assertType('array', $loosened);
    }

    /**
     * Anything but a closed base extended by a keyed shape is PHPStan's own reading: an open base
     * as it reads it, and as unresolvable the forms TypeBridge does not parse.
     *
     * @param OpenExtendedData $openExtended
     * @param OpenClashData $openClash
     * @param SealedOnOpenData $sealedOnOpen
     * @param ReversedData $reversed
     * @param ThreePartData $threePart
     * @param TupleData $tuple
     */
    public function unmerged(
        array $openExtended,
        array $openClash,
        array $sealedOnOpen,
        array $reversed,
        array $threePart,
        array $tuple,
    ): void {
        assertType('array{id: string, flag: bool, ...}', $openExtended);
        assertType('array', $openClash);
        assertType('array{flag: bool}&array{id: string, ...}', $sealedOnOpen);
        assertType('array', $reversed);
        assertType('array', $threePart);
        assertType('array', $tuple);
    }

    /**
     * A template parameter a shape bounds, and a shape keyed by one, are not merged into: they
     * would be resolved to the bound and no longer be the template.
     *
     * @template T of BaseData
     * @template K of 'k'
     *
     * @param T & array{extra: int} $templated
     * @param BaseData & array{K: int} $templateKeyed
     */
    public function templated(array $templated, array $templateKeyed): void
    {
        assertType('array', $templated);
        assertType('array', $templateKeyed);
    }
}
