import {Operator} from '../../models/Operator';
import {Criterion} from '../../models/Criterion';

export type AttributeTextCriterionOperator =
    | typeof Operator.EQUALS
    | typeof Operator.NOT_EQUAL
    | typeof Operator.CONTAINS
    | typeof Operator.DOES_NOT_CONTAIN
    | typeof Operator.STARTS_WITH
    | typeof Operator.IS_EMPTY
    | typeof Operator.IS_NOT_EMPTY;

export type AttributeTextCriterionState = {
    field: string;
    operator: AttributeTextCriterionOperator;
    value: string;
    locale: string | null;
    scope: string | null;
};

export type AttributeTextCriterion = Criterion<AttributeTextCriterionState>;
