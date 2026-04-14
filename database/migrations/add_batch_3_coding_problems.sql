-- Batch 3: Expanding to Data Structures & Advanced DP
-- Run this after previous migrations

INSERT INTO coding_problems (title, category, difficulty, problem_statement, constraints, example_input, example_output, concept_explanation, time_complexity, space_complexity) VALUES

-- Linked Lists (New Category)
('Reverse a Linked List', 'Linked Lists', 'Medium',
'Given the head of a singly linked list, reverse the list and return the new head.

Input: 1 -> 2 -> 3 -> 4 -> 5
Output: 5 -> 4 -> 3 -> 2 -> 1',
'The number of nodes in the list is in the range [0, 5000].
-5000 <= Node.val <= 5000',
'head = [1,2,3,4,5]',
'[5,4,3,2,1]',
'Iterative approach: Use three pointers (prev, curr, next). In each step, point curr.next to prev, then move all pointers one step forward. This teaches pointer manipulation and in-place list modification.',
'O(n)',
'O(1)'),

('Detect Cycle in Linked List', 'Linked Lists', 'Medium',
'Given head, the head of a linked list, determine if the linked list has a cycle in it.

Input: A linked list head
Output: true if there is a cycle, false otherwise',
'The number of nodes in the list is in the range [0, 10^4].
-10^5 <= Node.val <= 10^5',
'head = [3,2,0,-4], pos = 1 (cycle exists)',
'true',
'Floyd\'s Tortoise and Hare algorithm. Use two pointers moving at different speeds (slow moves 1 step, fast moves 2). If they ever meet, a cycle exists. This is a classic example of the two-pointer technique applied to lists.',
'O(n)',
'O(1)'),

-- Trees (New Category)
('Maximum Depth of Binary Tree', 'Trees', 'Easy',
'Find the maximum depth (height) of a binary tree. The maximum depth is the number of nodes along the longest path from the root node down to the farthest leaf node.

Input: Root of binary tree
Output: Integer depth',
'The number of nodes in the tree is in the range [0, 10^4].
-100 <= Node.val <= 100',
'root = [3,9,20,null,null,15,7]',
'3',
'Recursive DFS approach. The depth of a node is 1 + max(depth of left child, depth of right child). Base case: depth of a null node is 0. This introduces tree traversal and recursive problem-solving.',
'O(n)',
'O(h) where h is height'),

('Binary Tree Inorder Traversal', 'Trees', 'Easy',
'Given the root of a binary tree, return the inorder traversal of its nodes\' values. (Left -> Root -> Right)

Input: Root of binary tree
Output: Array of integers',
'The number of nodes in the tree is in the range [0, 100].
-100 <= Node.val <= 100',
'root = [1,null,2,3]',
'[1,3,2]',
'Inorder traversal visits the left subtree, then the root, then the right subtree. This is typically implemented using recursion or an explicit stack. It\'s the foundation for many binary search tree operations.',
'O(n)',
'O(n)'),

-- Dynamic Programming (More)
('Climbing Stairs', 'DP', 'Easy',
'You are climbing a staircase. It takes n steps to reach the top. Each time you can either climb 1 or 2 steps. In how many distinct ways can you climb to the top?

Input: Integer n
Output: Total distinct ways',
'1 <= n <= 45',
'n = 3',
'3 (1+1+1, 1+2, 2+1)',
'This is essentially the Fibonacci sequence. The number of ways to reach step n is the sum of ways to reach (n-1) and (n-2). This introduces state transition and optimization of recursive solutions.',
'O(n)',
'O(1) with optimization'),

('Coin Change', 'DP', 'Medium',
'You are given an integer array coins representing coins of different denominations and an integer amount. Return the fewest number of coins that you need to make up that amount.

Input: coins = [1, 2, 5], amount = 11
Output: 3 (5+5+1)',
'1 <= coins.length <= 12
1 <= coins[i] <= 2^31 - 1
0 <= amount <= 10^4',
'coins = [1,2,5], amount = 11',
'3',
'Bottom-up DP. Create an array dp of size amount+1. dp[i] stores the min coins for amount i. For each amount, try subtracting each coin denomination and take the minimum. dp[i] = min(dp[i], dp[i-coin] + 1).',
'O(amount * coins)',
'O(amount)'),

-- Sorting & Misc
('Bubble Sort Implementation', 'Sorting', 'Easy',
'Sort an array of integers using the Bubble Sort algorithm.

Input: Unsorted array
Output: Sorted array',
'1 <= array length <= 500',
'[64, 34, 25, 12, 22, 11, 90]',
'[11, 12, 22, 25, 34, 64, 90]',
'Repeatedly swap adjacent elements if they are in the wrong order. Small values "bubble" to the top. This is the simplest sorting algorithm, great for understanding the concept of nested loops and swapping.',
'O(n^2)',
'O(1)'),

('Palindrome Check', 'Strings', 'Easy',
'Check if a string is a palindrome (reads same forward and backward), ignoring case and non-alphanumeric characters.

Input: String
Output: Boolean',
'1 <= length <= 2*10^5',
'"A man, a plan, a canal: Panama"',
'true',
'Two-pointer approach. Move pointers from both ends towards the center. Ignore non-alphanumeric chars. If characters at pointers don\'t match (case-insensitive), it\'s not a palindrome.',
'O(n)',
'O(1)'),

('Power of x to n', 'Recursion', 'Medium',
'Implement pow(x, n), which calculates x raised to the power n (i.e., x^n).

Input: float x, int n
Output: float result',
'-100.0 < x < 100.0
-2^31 <= n <= 2^31-1',
'x = 2.0, n = 10',
'1024.0',
'Binary exponentiation (Fast Power). Instead of multiplying x, n times, use the property: x^n = (x^2)^(n/2) for even n, and x * (x^2)^((n-1)/2) for odd n. This reduces the number of multiplications significantly.',
'O(log n)',
'O(log n)'),

('Maximum Subarray Sum', 'Arrays', 'Medium',
'Find the contiguous subarray (containing at least one number) which has the largest sum and return its sum.

Input: Array of integers
Output: Maximum sum',
'1 <= length <= 10^5
-10^4 <= nums[i] <= 10^4',
'[-2,1,-3,4,-1,2,1,-5,4]',
'6 (4,-1,2,1)',
'Kadane\'s Algorithm. Maintain two variables: currentMax (sum including current element) and globalMax. currentMax = max(nums[i], currentMax + nums[i]). globalMax = max(globalMax, currentMax).',
'O(n)',
'O(1)'),

('Group Anagrams', 'Strings', 'Medium',
'Given an array of strings, group the anagrams together.

Input: Array of strings
Output: List of groups',
'1 <= strs.length <= 10^4
0 <= strs[i].length <= 100',
'["eat","tea","tan","ate","nat","bat"]',
'[["bat"],["nat","tan"],["ate","eat","tea"]]',
'Hash map with sorted keys. For each string, sort its characters to create a "key". Use a hash map where the key is the sorted string and the value is a list of its anagrams. This teaches hash mapping and string categorization.',
'O(n * k log k)',
'O(n * k)'),

('First Non-Repeating Character', 'Strings', 'Easy',
'Given a string s, find the first non-repeating character in it and return its index. If it does not exist, return -1.

Input: String s
Output: Integer index',
'1 <= s.length <= 10^5
s consists of only lowercase English letters',
'"leetcode"',
'0',
'Two-pass frequency counting. In the first pass, count the frequency of each character using an array or hash map. In the second pass, iterate through the string and return the first char with count 1.',
'O(n)',
'O(1) because alphabet size is fixed');
