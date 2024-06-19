"use strict";

module.exports = {
  up: async (queryInterface, Sequelize) => {
    await queryInterface.createTable("exercise_choices", {
      id: {
        allowNull: false,
        autoIncrement: true,
        primaryKey: true,
        type: Sequelize.INTEGER,
      },
      exercise_id: {
        type: Sequelize.INTEGER,
        allowNull: false,
      },
      choice_id: {
        type: Sequelize.INTEGER,
        allowNull: false,
      },
      choice_text: {
        type: Sequelize.TEXT,
        allowNull: false,
      },
      is_correct: {
        type: Sequelize.BOOLEAN,
        defaultValue: false,
      },
      created_at: {
        type: Sequelize.DATE,
      },
      updated_at: {
        type: Sequelize.DATE,
      },
    });

    await queryInterface.addConstraint("exercise_choices", {
      fields: ["exercise_id"],
      type: "foreign key",
      name: "fk_exercise_id",
      references: {
        table: "exercises",
        field: "id",
      },
      onDelete: "cascade",
      onUpdate: "cascade",
    });
  },

  down: async (queryInterface, Sequelize) => {
    await queryInterface.removeConstraint("exercise_choices", "fk_exercise_id");
    await queryInterface.dropTable("exercise_choices");
  },
};
