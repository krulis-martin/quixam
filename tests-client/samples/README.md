# The format of the question files

Each **question** template is stored in a separate file, located in the directory corresponding to its **question group**. The files use Markdown syntax with extra tags described below, which contain meta information for the test generation system. All files must be encoded in UTF-8.

Each piece of meta information in the files has the form `@metacmd <options>` and appears at the beginning of a separate line (the line contains only the meta tag, optionally its parameters, and nothing else). Like HTML tags, some of them can form block pairs `@tag` - `@/tag`. The main content must be inside a section block which is enclosed in one of the block tags (`@title`, `@text`, `@correct`, `@wrong`, `@item`, `@code`). Lines without meta tags located outside the main blocks are ignored completely.

### Setting the type of the @question

The question type specifier must appear in the file exactly once. The recommended position is at the very beginning of the file, but it can be located anywhere. Possible variants are:

- `@question single` _gen-answers_ (Single best answer question) It expects one `@correct` and multiple `@wrong` elements for generating answer options. The _gen-answers_ parameter determines how many answer options are offered to the student when the question template is instantiated.
- `@question multi` _gen-answers_ (Multi-choice question) It expects a sequence of answers in any combination of `@correct` and `@wrong`; the selected number of answers is shown.
- `@question num` _number_ (Numeric question) A question where the result is a single number (integer). The number argument is optional.
- `@question nums` _list of numeric results_ (Numeric sequence question) Similar to numeric question, but the result is an ordered list of numbers (integers). The numbers are separated by spaces.
- `@question nums-open` _min_ _max_ (Open numeric sequence question) Similar to numeric question, but it requires manual grading. The _min_ and _max_ parameters determine how many numbers the answer should contain (the student must provide at least _min_ numbers and at most _max_ numbers). If only one number is given, it is interpreted as both _min_ and _max_ (i.e., it sets the exact number of required numbers in the answer).
- `@question order` _min_ _max_ (Ordered multi-choice question) Similar to multi-choice, but the user needs to select (drag and drop) the correct items and put them in the right order. The items are specified using special `@item` sections. The min-max range is used to determine how many items are offered to the user when the question is instantiated.
- `@question text` _maxlen_ _regex_ (Text question) A question where the result is a text string. The _maxlen_ parameter defines the maximal allowed length of the answer string (it must not exceed 65536). The _regex_ parameter defines a regular expression that the answer must match (in PCRE syntax). If the regex is empty, the answer must be validated by the teacher manually (i.e., it is an open question).
- `@question dynamic` (Dynamically generated questions) These questions have no parameters; the body may then contain only `@title`, `@text`, and `@code`. It is a special question that contains imperative PHP code that is executed on instantiation and generates the question itself.

The numeric parameters in `num` and `nums` questions can use decimal text format, hexadecimal (with the C-style prefix `0x`), or binary (with the prefix `0b`). The encoding is not relevant, students can answer in any base they like.

All questions should have a name and text. Single and multi-choice (including ordered) questions also have answer options specified in additional blocks. These blocks are explained below.

### Name of the question

Name of the question is in the `@title` block. It is recommended to place this block right after the `@question` tag, but it can be located anywhere in the file. The content may use language (translation) variants (see below).

Example:

```
@title
Technological names
@/title
```

### Question text

The main question text is in the `@text` block. It is recommended to place this block right after the `@title` block, but it can be located anywhere in the file. The content may use language (translation) variants (see below).

Example:

```
@text
@en
What is the origin of the name "Bluetooth" that refers to the wireless technology standard for exchanging data over short distances?
@cs
Jaký je původ názvu "Bluetooth", který se vztahuje k bezdrátné technologii pro výměnu dat na krátké vzdálenosti?
@/text
```

Optionally, the question text may contain `@correct` section (which can be localized in the same way as `@text`) that contains an example of a correct (sample) answer. The correct answer is not used for grading, but may be used to guide teacher who will grade the question manually.

### Answers

There may be several different kinds of answers, depending on the question type. Each is represented by a block starting with a special tag. The content may use language (translation) variants (see below).

- `@correct` - beginning of a correct answer option text
- `@wrong` - beginning of an incorrect answer option text
- `@else` - a mutually exclusive answer used to interconnect correct and incorrect options (only one of them will be offered to the user in the question).

The `@else` is useful only in the case of multi-choice answers, where an arbitrary sub-selection of correct answer options is used when the question is generated. In single best answer questions, the correct answer must always be present, so it makes no sense to pair it with a mutually exclusive wrong answer (that would never be selected).

Examples:

```
@correct
This is the correct answer (select me!)
@/correct

@wrong
This is not the correct answer (don't select me!)
@/wrong

@correct
Good answer.
@else
Bad answer.
@/wrong
```

### Items for ordered multi-choice (drag'n'drop) questions

All answer options are specified using `@item` tags instead of `@correct/@wrong` tags (and unlike the previous types, they do not have `@else` either). Additionally, the opening tag of the item holds additional parameters (up to three):

`@item [ <correct-order> [ <flags> [ <group> ] ] ]`

- _correct-order_ is either a number (used to determine whether the selected items are in the correct order) or the `null` literal if the item should not be selected (i.e., it represents an incorrect answer option). The default value is `null` if no parameters are given. Multiple items may have the same correct-order number; in that case, their relative order is irrelevant (the student may place them in any order relative to each other).
- _flags_ are encoded as sequence of chars (no whitespace), each char represents one flag:
  - `!` stands for _mandatory_ (this item is always present among the options when the question template is instantiated)
  - `+` stands for _preselected_ (if present in the question instance, the item is pre-selected into the initial blank answer as a suggestion for the user)
- _group_ is a string identifier referring to a group to which the item belongs. The purpose of a group is to indicate that some items should be selected together (an entire group is either added or not added when the question is instantiated). Group identifiers must not consist solely of flags' characters (so we can distinguish whether `<flags>` are present or not during parsing) -- i.e., `!+` is a bad group identifier, `!a+` is fine.

### Language variants

In the textual blocks (`@title`, `@text`, `@correct`, `@wrong`, `@item`), the text can be localized. Users may switch between languages during the test; only the selected translation is displayed. We support English (`en`) and Czech (`cs`) at the moment. Text outside a localized section is added to all localizations.

Tags `@en` and `@cs` start a localized section in the corresponding language. The localized section is ended implicitly, if another localization section is opened, or when the wrapping text block is closed. You may also terminate opened localized section by `@/en` and `@/cs` tags (after which an unlocalized section will follow).

Example:

```
@text
@en
What is the origin of the name
@cs
Jaký je původ názvu
@/cs
"Bluetooth"?
@/text
```

This will render in English as

```
What is the origin of the name
"Bluetooth"?
```

and in Czech as

```
Jaký je původ názvu
"Bluetooth"?
```

Note that the text is in Markdown, so the line break will be ignored in the rendering into HTML for visualization.

### Preprocessor

To prevent text duplication and to allow modularization of the question templates, we have a simple preprocessor that processes the question files before parsing. The preprocessor has two functions: file includes (like in C) and text snippets (like trivial macros that can be defined and pasted).

`@include <path>` inserts a file like a C macro preprocessor (i.e., includes are processed before parsing). The `<path>` is a relative or absolute path to the included file and must not contain any whitespace. Relative paths are resolved using the parent file's directory as the base path. Includes may be recursive, but they must not form a cycle (there is no prevention like header guards or `#pragma once`). Include does not validate the contents in any way, so it is fine to insert one file containing an opening tag and another file containing the corresponding closing tag. The final product of the (transitive) includes must be valid.

A **snippet** is a piece of text that can be pasted into various places in the text. It is useful for repeated content (for example common instructions that repeat in every question from one group). Like a macro, a snippet is substituted before the subsequent processing of the file content (so it may contain arbitrary `@` directives, except nested snippet declarations and includes).

The snippet is wrapped in a `@snippet` block tag; the opening tag must be provided with a unique snippet identifier. The snippet is inserted into the code using the `@paste <snippet-id>` tag. A snippet must be declared before it is used.

Example:

```
@snippet instructions-1
Try to answer this question as accurately as you can.
@/snippet
```

And then somewhere else in the file:

```
@text
...
@paste instructions-1
@/text
```

### Comments

Anything located in between the tags `@comment` and `@/comment` is ignored completely. If `@/comment` is missing, everything up to the end of the file is commented out (that is, processing does not continue any further, even if the file were, for example, included).

### Dynamic questions

Questions of type `dynamic` do not have a list of answers. Instead, they should contain _code_ (inside the `@code` tag) that generates the question itself. This code can also generate questions of various types (`single`, `multi`, `num`, ...); see the Quixam interface. The code is written in PHP and is interpreted entirely in the context of a method that is executed in an instance of a special class. This class already provides certain protected methods that serve as an interface for modifying text, adding answers, and so on. In the code, it is possible to use only these API methods and common functions for working with arrays, strings, numbers, and similar values. Functions and classes for working with files, databases, networks, and so on are not available.
