import React, {FC} from 'react';
import {renderHook} from '@testing-library/react-hooks';
import {ThemeProvider} from 'styled-components';
import {pimTheme} from 'akeneo-design-system';
import {DependenciesProvider} from '@akeneo-pim-community/legacy-bridge';
import {
  BackendCategoryTree,
  CategoryTreeModel,
  CategoryTreeProvider,
  useCategoryTreeNode,
} from '@akeneo-pim-community/settings-ui';
import {aBackendCategoryTree, aCategoryTree} from '../../../utils/provideCategoryHelper';
import {act} from 'react-test-renderer';

const DefaultProviders: FC<{root: CategoryTreeModel}> = ({children, root}) => (
  <DependenciesProvider>
    <ThemeProvider theme={pimTheme}>
      <CategoryTreeProvider root={root}>{children}</CategoryTreeProvider>
    </ThemeProvider>
  </DependenciesProvider>
);

const renderUseCategoryTree = (categoryId: number, root: CategoryTreeModel) => {
  const wrapper: FC = ({children}) => <DefaultProviders root={root}>{children}</DefaultProviders>;
  return renderHook(({categoryId}: {categoryId: number}) => useCategoryTreeNode(categoryId), {
    initialProps: {categoryId},
    wrapper,
  });
};

describe('useCategoryTreeNode', () => {
  test('it returns default values', () => {
    const root = aCategoryTree('a_root', ['cat_1', 'cat_2', 'cat_3'], true, false, 1234);
    const {result} = renderUseCategoryTree(1234, root);
    expect(result.current.node).not.toBeNull();

    expect(result.current.children.length).toBe(3);
    expect(result.current.moveTarget).toBeNull();
    expect(result.current.setMoveTarget).toBeDefined();
    expect(result.current.draggedCategory).toBeNull();
    expect(result.current.setDraggedCategory).toBeDefined();
    expect(result.current.hoveredCategory).toBeNull();
    expect(result.current.setHoveredCategory).toBeDefined();
    expect(result.current.loadChildren).toBeDefined();
    expect(result.current.moveTo).toBeDefined();
    expect(result.current.getCategoryPosition).toBeDefined();
  });

  test('it returns the root node', () => {
    const root = aCategoryTree('a_root', ['cat_1', 'cat_2', 'cat_3'], true, false, 1234);
    const {result} = renderUseCategoryTree(1234, root);
    expect(result.current.node).not.toBeNull();
    expect(result.current.node?.data).toEqual(root);
    expect(result.current.node?.identifier).toEqual(1234);
    expect(result.current.node?.childrenIds.length).toBe(3);
    expect(result.current.node?.type).toBe('root');
  });

  test('it returns an existing node', () => {
    const root = aCategoryTree('a_root', ['cat_1', 'cat_2', 'cat_3'], true, false, 1234);
    const {result} = renderUseCategoryTree(1, root);
    expect(result.current.node?.identifier).toEqual(1);
    expect(result.current.node?.childrenIds.length).toBe(0);
    expect(result.current.node?.type).toBe('node');
    expect(result.current.node?.label).toBe('[cat_1]');
    expect(result.current.node?.data.code).toBe('cat_1');
  });
});

describe('useCategoryTreeNode > load children', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.resetAllMocks();
  });

  afterAll(() => {
    jest.restoreAllMocks();
  });

  test('it loads children', async () => {
    const childrenList: BackendCategoryTree[] = [
      aBackendCategoryTree('a_category', [], false, 1111),
      aBackendCategoryTree('a_second_category', [], false, 2222),
      aBackendCategoryTree('a_third_category', [], false, 3333),
    ];

    // @ts-ignore
    jest.spyOn(global, 'fetch').mockResolvedValue({
      json: () => Promise.resolve(childrenList),
    });

    const root = aCategoryTree('a_root', [], true, false, 1234);
    const {result} = renderUseCategoryTree(1234, root);

    expect(result.current.node?.childrenStatus).toBe('idle');
    expect(result.current.node?.childrenIds.length).toBe(0);
    expect(result.current.children.length).toBe(0);

    await act(async () => {
      result.current.loadChildren();
    });

    expect(result.current.node?.childrenStatus).toBe('loaded');
    expect(result.current.node?.childrenIds).toEqual([1111, 2222, 3333]);
    expect(result.current.children.length).toBe(3);
  });

  test(' it returns an empty list of children when the loading failed', async () => {
    // @ts-ignore
    jest.spyOn(global, 'fetch').mockReject(new Error('An unexpected server error'));
    const root = aCategoryTree('a_root', [], true, false, 1234);
    const {result} = renderUseCategoryTree(1234, root);

    expect(result.current.node?.childrenStatus).toBe('idle');

    await act(async () => {
      result.current.loadChildren();
    });

    expect(result.current.node?.childrenStatus).toBe('idle');
    expect(result.current.node?.childrenIds).toEqual([]);
    expect(result.current.children.length).toBe(0);
  });
});

describe('useCategoryTreeNode > move category', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.resetAllMocks();
  });

  afterAll(() => {
    jest.restoreAllMocks();
  });

  test('it moves a category before', () => {
    const root = aCategoryTree('a_root', ['cat_1', 'cat_2', 'cat_3'], true, false, 1234);
    const {result} = renderUseCategoryTree(1234, root);

    expect(result.current.node?.childrenIds).toEqual([1, 2, 3]);
    act(() => {
      result.current.moveTo(1, {identifier: 3, parentId: 1234, position: 'before'});
    });
    expect(result.current.node?.childrenIds).toEqual([2, 1, 3]);
  });

  test('it moves a category after', () => {
    const root = aCategoryTree('a_root', ['cat_1', 'cat_2', 'cat_3'], true, false, 1234);
    const {result} = renderUseCategoryTree(1234, root);

    expect(result.current.node?.childrenIds).toEqual([1, 2, 3]);
    act(() => {
      result.current.moveTo(1, {identifier: 3, parentId: 1234, position: 'after'});
    });
    expect(result.current.node?.childrenIds).toEqual([2, 3, 1]);
  });

  test('it moves a category in leaf', () => {
    const root = aCategoryTree('a_root', ['cat_1', 'cat_2', 'cat_3'], true, false, 1234);
    const {result} = renderUseCategoryTree(1234, root);

    expect(result.current.node?.childrenIds).toEqual([1, 2, 3]);
    expect(result.current.children[1].type).toBe('leaf');

    act(() => {
      result.current.moveTo(1, {identifier: 2, parentId: 1234, position: 'in'});
    });

    expect(result.current.node?.childrenIds).toEqual([2, 3]);
    expect(result.current.children[0].childrenIds).toEqual([1]);
    expect(result.current.children[0].type).toBe('node');
    expect(result.current.children[0].identifier).toBe(2);
  });

  test('it moves a category in parent category', async () => {
    const root = aCategoryTree('a_root', ['cat_1', 'cat_2', 'cat_3'], true, false, 1234);
    const {result, rerender} = renderUseCategoryTree(1234, root);

    const childrenList: BackendCategoryTree[] = [
      aBackendCategoryTree('a_child_category', [], false, 1111),
      aBackendCategoryTree('a_second_child_category', [], false, 2222),
    ];

    // @ts-ignore
    jest.spyOn(global, 'fetch').mockResolvedValue({
      json: () => Promise.resolve(childrenList),
    });

    // Check initial root category
    expect(result.current.node?.identifier).toEqual(1234);
    expect(result.current.node?.type).toEqual('root');
    expect(result.current.node?.childrenIds).toEqual([1, 2, 3]);
    expect(result.current.children[2].type).toBe('node');

    act(() => {
      rerender({categoryId: 3});
    });

    // Check the "cat_3" category to process the move from "cat_1" in "cat_3"
    expect(result.current.node?.identifier).toEqual(3);
    expect(result.current.node?.type).toEqual('node');
    expect(result.current.node?.childrenIds).toEqual([]);

    await act(async () => {
      result.current.moveTo(1, {identifier: 3, parentId: 1234, position: 'in'});
    });

    expect(result.current.node?.identifier).toBe(3);
    expect(result.current.node?.childrenIds).toEqual([1, 1111, 2222]);
    expect(result.current.node?.type).toBe('node');

    act(() => {
      rerender({categoryId: 1234});
    });

    // Check root category after move
    expect(result.current.node?.identifier).toEqual(1234);
    expect(result.current.node?.type).toEqual('root');
    expect(result.current.node?.childrenIds).toEqual([2, 3]);
    expect(result.current.children[1].type).toBe('node');
  });
});
