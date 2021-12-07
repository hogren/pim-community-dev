import React from 'react';
import styled from 'styled-components';
import {ProgressBar, Table} from 'akeneo-design-system';
import {
  JobExecutionRow,
  getStepExecutionRowTrackingLevel,
  getStepExecutionRowTrackingPercent,
  getStepExecutionRowTrackingProgressLabel,
} from '../../models';
import {useTranslate} from '@akeneo-pim-community/shared';

const Container = styled.div`
  display: grid;
  grid-auto-flow: column;
  grid-gap: 4px;
  grid-auto-columns: 1fr;
  flex: 1;
  min-width: 100px;
  max-width: 200px;
`;

type ProgressCellProps = {
  jobExecutionRow: JobExecutionRow;
};

const ProgressCell = ({jobExecutionRow}: ProgressCellProps) => {
  const translate = useTranslate();
  const currentStep = jobExecutionRow.tracking.steps[jobExecutionRow.tracking.current_step - 1];

  return (
    <Table.Cell title={getStepExecutionRowTrackingProgressLabel(translate, jobExecutionRow, currentStep)}>
      <Container>
        {[...Array(jobExecutionRow.tracking.total_step)].map((_, stepIndex) => {
          if ('STARTING' === jobExecutionRow.status) {
            return <ProgressBar key={stepIndex} percent="indeterminate" level="primary" />;
          }

          const step = jobExecutionRow.tracking.steps[stepIndex] ?? null;

          return null === step ? (
            <ProgressBar key={stepIndex} level="primary" percent={0} />
          ) : (
            <ProgressBar
              key={stepIndex}
              level={getStepExecutionRowTrackingLevel(step)}
              percent={getStepExecutionRowTrackingPercent(step)}
            />
          );
        })}
      </Container>
    </Table.Cell>
  );
};

export {ProgressCell};
