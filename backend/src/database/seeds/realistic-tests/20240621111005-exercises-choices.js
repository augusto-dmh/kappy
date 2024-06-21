"use strict";

module.exports = {
  up: async (queryInterface, Sequelize) => {
    let idCounter = 1;
    const exerciseChoices = [
      // Choices for "Variáveis e Sintaxe Básica"
      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "int numero;",
        is_correct: true,
        exercise_id: 1,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "float numero;",
        is_correct: false,
        exercise_id: 1,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "char numero;",
        is_correct: false,
        exercise_id: 1,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "boolean numero;",
        is_correct: false,
        exercise_id: 1,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "int x = 10;",
        is_correct: true,
        exercise_id: 2,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "int x == 10;",
        is_correct: false,
        exercise_id: 2,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "int x = 10",
        is_correct: false,
        exercise_id: 2,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "int x <- 10;",
        is_correct: false,
        exercise_id: 2,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "int",
        is_correct: false,
        exercise_id: 3,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "boolean",
        is_correct: false,
        exercise_id: 3,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "char",
        is_correct: false,
        exercise_id: 3,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "double",
        is_correct: true,
        exercise_id: 3,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "public static void main(String[] args)",
        is_correct: true,
        exercise_id: 4,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "public void main(String args)",
        is_correct: false,
        exercise_id: 4,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "public static main(String[] args)",
        is_correct: false,
        exercise_id: 4,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "public void static main(String[] args)",
        is_correct: false,
        exercise_id: 4,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "2",
        is_correct: false,
        exercise_id: 5,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "5",
        is_correct: false,
        exercise_id: 5,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "8",
        is_correct: true,
        exercise_id: 5,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "15",
        is_correct: false,
        exercise_id: 5,
        created_at: new Date(),
        updated_at: new Date(),
      },

      // Choices for "Boolean, Strings e Sintaxe"
      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "true",
        is_correct: true,
        exercise_id: 6,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "false",
        is_correct: false,
        exercise_id: 6,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "10",
        is_correct: false,
        exercise_id: 6,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "20",
        is_correct: false,
        exercise_id: 6,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "true",
        is_correct: true,
        exercise_id: 7,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "false",
        is_correct: false,
        exercise_id: 7,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "15",
        is_correct: false,
        exercise_id: 7,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "0",
        is_correct: false,
        exercise_id: 7,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "true",
        is_correct: false,
        exercise_id: 8,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "false",
        is_correct: true,
        exercise_id: 8,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "Hello",
        is_correct: false,
        exercise_id: 8,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "hello",
        is_correct: false,
        exercise_id: 8,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "true",
        is_correct: true,
        exercise_id: 9,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "false",
        is_correct: false,
        exercise_id: 9,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "5",
        is_correct: false,
        exercise_id: 9,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "3",
        is_correct: false,
        exercise_id: 9,
        created_at: new Date(),
        updated_at: new Date(),
      },

      {
        id: idCounter++,
        choice_id: 1,
        choice_text: "true",
        is_correct: false,
        exercise_id: 10,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 2,
        choice_text: "false",
        is_correct: true,
        exercise_id: 10,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 3,
        choice_text: "Java",
        is_correct: false,
        exercise_id: 10,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: idCounter++,
        choice_id: 4,
        choice_text: "null",
        is_correct: false,
        exercise_id: 10,
        created_at: new Date(),
        updated_at: new Date(),
      },
    ];

    await queryInterface.bulkInsert("exercise_choices", exerciseChoices, {});
  },

  down: async (queryInterface, Sequelize) => {
    await queryInterface.bulkDelete("exercise_choices", null, {});
  },
};