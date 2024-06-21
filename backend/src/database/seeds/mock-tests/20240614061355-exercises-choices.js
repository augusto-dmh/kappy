"use strict";

const { faker } = require("@faker-js/faker");

module.exports = {
  up: async (queryInterface, Sequelize) => {
    const exercises = await queryInterface.sequelize.query(
      `SELECT id from exercises;`
    );

    const exerciseChoicesData = [];
    let idCounter = 1;

    exercises[0].forEach((exercise) => {
      for (let i = 0; i < 4; i++) {
        exerciseChoicesData.push({
          id: idCounter++,
          choice_id: i + 1,
          choice_text: faker.lorem.words(3),
          is_correct: i === 0,
          exercise_id: exercise.id,
          created_at: new Date(),
          updated_at: new Date(),
        });
      }
    });

    await queryInterface.bulkInsert(
      "exercise_choices",
      exerciseChoicesData,
      {}
    );
  },

  down: async (queryInterface, Sequelize) => {
    await queryInterface.bulkDelete("exercise_choices", null, {});
  },
};
