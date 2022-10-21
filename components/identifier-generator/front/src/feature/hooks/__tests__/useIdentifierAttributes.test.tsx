import {waitFor} from '@testing-library/react';
import {renderHook} from '@testing-library/react-hooks';
import {useIdentifierAttributes} from '../';
import {createWrapper} from '../../tests/hooks/config/createWrapper';
import {FlattenAttribute} from '../../models/';

describe('useIdentifierAttributes', () => {
  beforeEach(() => {
    jest.spyOn(global, 'fetch').mockResolvedValue({
      ok: true,
      json: () => Promise.resolve([{code: 'sku', label: 'Sku'}]),
    } as Response);
  });

  test('it retrieves identifier attribute list', async () => {
    const {result} = renderHook<
      null,
      {
        isSuccess: boolean;
        data?: FlattenAttribute[] | undefined;
        error: Error | null;
      }
    >(() => useIdentifierAttributes(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => result.current.isSuccess);

    expect(result.current.data).toBeDefined();
    expect(result.current.data).toEqual([{code: 'sku', label: 'Sku'}]);
  });
});
